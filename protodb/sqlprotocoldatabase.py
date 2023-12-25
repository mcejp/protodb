#
# Copyright (C) 2016-2023 Martin Cejp
#
# This file is part of ProtoDB.
#
# ProtoDB is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# ProtoDB is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with ProtoDB.  If not, see <http://www.gnu.org/licenses/>.

# This is all wrong and quite misguided. Much more work should be left to the database engine
# and there should be no caching here.
# Should have just used SQLAlchemy...

import sys
from datetime import timedelta
from enum import Enum, auto
import itertools
from typing import Iterable, Dict, Optional, Set, Tuple

import mysql.connector

from . import model
from . import ProtocolDatabase
from .protocoldatabase import InvalidScopeError


class ChangelogAction(Enum):
    DELETE = auto()


class ChangelogEntityType(Enum):
    MESSAGE = auto()
    NODE = auto()


class Package(model.Package):
    db: 'SqlProtocolDatabase'

    def __init__(self, db: 'SqlProtocolDatabase', id: int, name: str):
        self.db = db
        self.id = id
        self.name = name
        self.fully_qualified_name = name


class Bus(model.Bus):
    db: 'SqlProtocolDatabase'

    id: int

    def __init__(self, db: 'SqlProtocolDatabase', id: int, package_id: int, name: str, dbc_id: Optional[int],
                 bitrate: Optional[int]):
        self.name = name
        self.dbc_id = dbc_id
        self.bitrate = bitrate

        self.db = db
        self.id = id

        self.package_id = package_id

    def __getattr__(self, name):
        if name == 'fully_qualified_name':
            self.fully_qualified_name = f'{self.package.fully_qualified_name}.{self.name}'
        elif name == 'package':
            self.package = self.db.get_package_by_id(self.package_id)

        return super().__getattribute__(name)


class Node(model.Node):
    db: 'SqlProtocolDatabase'

    def __init__(self, db: 'SqlProtocolDatabase', id: int, package_id: int, name: str, description: str):
        self.name = name
        self.description = description

        self.db = db
        self.id = id
        self.package_id = package_id

    def __getattr__(self, name):
        if name == 'fully_qualified_name':
            self.fully_qualified_name = f'{self.package.fully_qualified_name}.{self.name}'
        elif name == 'package':
            self.package = self.db.get_package_by_id(self.package_id)

        return super().__getattribute__(name)


class NodeBusLink(model.NodeBusLink):
    db: 'SqlProtocolDatabase'

    id: int
    bus_id: int
    node_id: int

    def __init__(self, db: 'SqlProtocolDatabase', id: int, bus_id: int, node_id: int, note: str):
        self.db = db

        self.id = id
        self.bus_id = bus_id
        self.node_id = node_id
        self.note = note

    def __eq__(self, other):
        return isinstance(other, NodeBusLink) and self.id == other.id

    def __getattr__(self, name):
        if name == 'bus':
            self.bus = self.db.get_bus_by_id(self.bus_id)

        return super().__getattribute__(name)


class Message(model.Message):
    db: 'SqlProtocolDatabase'

    id: int
    bus_id: Optional[int]
    unit_id: int

    def __init__(self, db: 'SqlProtocolDatabase', id: int, bus_id: Optional[int], frame_type: model.FrameType,
                 can_id: Optional[int], unit_id: int, name: str, description: str, timeout: Optional[timedelta],
                 tx_period: Optional[timedelta]):
        self.name = name
        self.description = description
        self.frame_type = frame_type
        self.can_id = can_id
        self.timeout = timeout
        self.tx_period = tx_period

        self.db = db
        self.id = id
        self.bus_id = bus_id
        self.unit_id = unit_id

    def __getattr__(self, name):
        if name == 'bus':
            self.bus = self.db.get_bus_by_id(self.bus_id) if self.bus_id is not None else None
        elif name == 'fields':
            self.fields = self.db.load_message_fields(self)
        elif name == 'fully_qualified_name':
            self.fully_qualified_name = f'{self.unit.fully_qualified_name}.{self.name}'
        elif name == 'unit':
            self.unit = self.db.get_unit_by_id(self.unit_id)

        return super().__getattribute__(name)


class MessageField(model.MessageField):
    message: Message

    def __init__(self, message: Message, name: str, description: str,
                 type: model.MessageFieldType, size_in_bits: int, array_length: int,
                 unit: Optional[str], factor: Optional[str], offset: Optional[str],
                 min: Optional[str], max: Optional[str],
                 ):
        super().__init__(name=name, description=description, type=type, size_in_bits=size_in_bits, array_length=array_length,
                         unit=unit, factor=factor, offset=offset, min=min, max=max)

        self.message = message
        self.fully_qualified_name = f'{message.fully_qualified_name}.{name if name else "<reserved>"}'


class EnumType(model.EnumType):
    id: int

    def __init__(self, name: str, description: str, items: Iterable[model.EnumTypeItem], node: Node, id: int):
        self.name = name
        self.description = description
        self.items = items

        self.node = node
        self.fully_qualified_name = f"{node.fully_qualified_name}.{name}"
        self.id = id


def build_filter(query: str, kwargs: dict) -> Tuple[str, Tuple]:
    """ Dynamically append WHERE clause to query and populate argument tuple """

    args = []

    for key, value in kwargs.items():
        if value is None:
            continue

        if ' WHERE ' not in query.upper():
            query += ' WHERE '
        else:
            query += ' AND '

        query += f'`{key}` = %s'

        args.append(str(value))

    return query, tuple(args)


class SqlProtocolDatabase(ProtocolDatabase):
    buses_by_id: Dict[int, Bus] = {}
    enum_types_by_id: Dict[int, EnumType] = {}
    messages_by_id: Dict[int, Message] = {}
    packages_by_id: Dict[int, Package] = {}
    packages_by_name: Dict[str, Package] = {}
    units_by_id: Dict[int, Node] = {}

    all_buses: Iterable[Bus] = None
    all_enum_types: Iterable[EnumType] = None
    all_messages: Iterable[Message] = None
    all_units: Iterable[Node] = None
    have_all_packages: bool = False         # put them in packages_by_*

    conn: mysql.connector.MySQLConnection
    _connection_kwargs: dict

    def __init__(self, conn_string, run_consistency_checks=True):
        kwargs = {key: value for (key, value) in
                  [pair.split('=') for pair in conn_string.replace('mysql:', '').split(';')]}

        if 'dbname' in kwargs:
            kwargs['database'] = kwargs['dbname']
            del kwargs['dbname']

        self._connection_kwargs = kwargs
        self.conn = mysql.connector.connect(**kwargs)

        if run_consistency_checks:
            self.run_consistency_checks()

    def delete(self, entity: model.Entity, who_changed: str) -> None:
        if isinstance(entity, Message):
            self._delete_message_by_id(entity.id, who_changed=who_changed)
        elif isinstance(entity, Node):
            self._delete_node_by_id(entity.id, who_changed=who_changed)
        else:
            raise InvalidScopeError()

    def get_associated_messages(self, bus: Bus) -> Iterable[model.Message]:
        return self.get_messages_with_filter(bus_id=bus.id)

    def get_bus_by_id(self, bus_id: int, db_row=None) -> Bus:
        if bus_id not in self.buses_by_id:
            if db_row is None:
                cursor = self._make_cursor(dictionary=True)
                cursor.execute('SELECT id, package_id, name, dbc_id, bitrate FROM bus WHERE id = %s', (bus_id,))
                db_row = cursor.fetchone()

            if db_row is None:
                raise Exception(f'Cannot find Bus(id={bus_id})')

            self.buses_by_id[bus_id] = Bus(self, **db_row)

        return self.buses_by_id[bus_id]

    def get_bus_nodes(self, bus: Bus) -> Iterable[Node]:
        # TODO: better caching

        cursor = self._make_cursor(dictionary=True)
        cursor.execute('SELECT DISTINCT node.id, node.description, node.package_id, node.name FROM node_bus '
                       'LEFT JOIN node ON node.id = node_bus.node_id WHERE bus_id = %s', (bus.id,))

        nodes = set()

        for row in cursor:
            nodes.add(self.get_unit_by_id(row["id"], db_row=row))

        return sorted(nodes, key=lambda node: node.fully_qualified_name)

    def get_buses(self, scope: Optional[Package] = None) -> Iterable[Bus]:
        if scope is not None:
            return self.get_buses_with_filter(package_id=scope.id)
        else:
            if self.all_buses is None:
                self.all_buses = self.get_buses_with_filter()

            return self.all_buses

    def get_buses_with_filter(self, name: Optional[str] = None, package_id: Optional[int] = None) -> Iterable[Bus]:
        buses = set()

        query, args = build_filter('SELECT id, package_id, name, dbc_id, bitrate FROM bus',    # no "valid" field
                                   dict(name=name, package_id=package_id))
        cursor = self._make_cursor(dictionary=True)
        cursor.execute(query, args)

        for row in cursor:
            buses.add(self.get_bus_by_id(row['id'], db_row=row))

        return buses

    def get_enum_type(self, type: model.EnumMessageFieldType):
        enum_types = self.get_enum_types_with_filter(name=type.enum, node_id=type.node.id)

        if len(enum_types) != 0:
            assert len(enum_types) == 1
            enum_type, = enum_types
            return enum_type

        raise Exception(f'No enum type named "{name}" found in node {node.fully_qualified_name}')

    def get_enum_type_by_id(self, enum_type_id: int, db_row=None) -> EnumType:
        if enum_type_id not in self.enum_types_by_id:
            if db_row is None:
                cursor = self._make_cursor(dictionary=True)
                cursor.execute('SELECT id, description, name, node_id FROM enum_type '
                               'WHERE id = %s', (enum_type_id,))

                db_row = cursor.fetchone()

            if db_row is None:
                raise Exception(f'Cannot find EnumType(id={enum_type_id})')

            node = self.get_unit_by_id(db_row["node_id"])
            del db_row["node_id"]

            cursor = self._make_cursor(dictionary=True, buffered=True)
            cursor.execute("SELECT description, name, value FROM enum_item "
                           "WHERE enum_type_id = %s", (enum_type_id,))

            items = [model.EnumTypeItem(**row) for row in cursor.fetchall()]

            self.enum_types_by_id[enum_type_id] = EnumType(**db_row, node=node, items=items)

        return self.enum_types_by_id[enum_type_id]

    def get_enum_types(self, scope: Optional[model.Entity] = None) -> Iterable[EnumType]:
        if scope is not None:
            if isinstance(scope, Node):
                return self.get_enum_types_with_filter(node_id=scope.id)
            elif isinstance(scope, Package):
                nodes = self.get_nodes_with_filter(package_id=scope.id)
                return itertools.chain(*[self.get_enum_types_with_filter(node_id=node.id) for node in nodes])
            else:
                raise InvalidScopeError()
        else:
            if self.all_enum_types is None:
                self.all_enum_types = self.get_enum_types_with_filter()

            return self.all_enum_types

    def get_enum_types_with_filter(self, name: Optional[str] = None, node_id: Optional[int] = None) -> Iterable[EnumType]:
        enum_types = set()

        query, args = build_filter('SELECT id, description, node_id, name FROM enum_type ',
                                   dict(name=name, node_id=node_id))
        # cursor must be buffered due to get_enum_type_by_id
        cursor = self._make_cursor(dictionary=True, buffered=True)
        cursor.execute(query, args)

        for row in cursor:
            enum_types.add(self.get_enum_type_by_id(row['id'], db_row=row))

        return enum_types

    def get_message_by_id(self, message_id: int, db_row=None) -> Message:
        if message_id not in self.messages_by_id:
            if db_row is None:
                cursor = self._make_cursor(dictionary=True)
                cursor.execute('SELECT id, node_id AS unit_id, name, description, bus_id, can_id, can_id_type, timeout, tx_period FROM message '
                               'WHERE id = %s AND valid = 1', (message_id,))

                db_row = cursor.fetchone()

            if db_row is None:
                raise Exception(f'Cannot find Message(id={message_id})')

            def make_frame_type(can_id_type):
                if can_id_type == "DIRECT_EXTENDED":
                    return model.FrameType.CAN_EXT
                else:
                    return model.FrameType.CAN_STD

            if db_row['can_id_type'] == "UNDEF" and db_row['can_id'] is not None:
                # raise ValueError("Expected null ID")

                # coerce ID to null until database fully migrated (issue #98)
                # mirrors candb\model\Message::set_can_id
                db_row['can_id'] = None

            db_row = {**db_row,
                      'timeout': timedelta(milliseconds=db_row['timeout']) if db_row['timeout'] is not None else None,
                      'tx_period': timedelta(milliseconds=db_row['tx_period']) if db_row['tx_period'] is not None else None,
                      'frame_type': make_frame_type(db_row['can_id_type']),
                      }
            del db_row['can_id_type']
            self.messages_by_id[message_id] = Message(self, **db_row)

        return self.messages_by_id[message_id]

    def get_messages(self, scope=None) -> Iterable[Message]:
        if scope is not None:
            if isinstance(scope, Node):
                return self.get_messages_with_filter(node_id=scope.id)
            elif isinstance(scope, Package):
                nodes = self.get_nodes_with_filter(package_id=scope.id)
                return itertools.chain(*[self.get_messages_with_filter(node_id=node.id) for node in nodes])
            else:
                raise InvalidScopeError()
        else:
            if self.all_messages is None:
                self.all_messages = self.get_messages_with_filter()

            return self.all_messages

    def get_message_fields(self, scope: Optional[model.Entity] = None) -> Iterable[model.MessageField]:
        if scope is not None:
            if isinstance(scope, Message):
                return scope.fields
            elif isinstance(scope, Node) or isinstance(scope, Package):
                messages = self.get_messages(scope=scope)
                return itertools.chain(*[message.fields for message in messages])
            else:
                raise InvalidScopeError()
        else:
            fields = set()

            for message in self.get_messages():
                fields.update(message.fields)

            return fields

    def get_messages_with_filter(self, bus_id: Optional[int] = None, name: Optional[str] = None, node_id: Optional[int] = None) -> Iterable[Message]:
        messages = set()

        query, args = build_filter('SELECT id, node_id AS unit_id, name, description, can_id, can_id_type, bus_id, timeout, tx_period FROM message '
                                   'WHERE valid = 1', dict(bus_id=bus_id, name=name, node_id=node_id))
        cursor = self._make_cursor(dictionary=True)
        cursor.execute(query, args)

        for row in cursor:
            messages.add(self.get_message_by_id(row['id'], db_row=row))

        return messages

    def get_node_bus_links(self, node: Node) -> Iterable[NodeBusLink]:
        # TODO: caching

        cursor = self._make_cursor(dictionary=True)
        cursor.execute('SELECT id, node_id, bus_id, note FROM node_bus WHERE node_id = %s', (node.id,))

        # FIXME: probably cannot do this -- database-linked objects should be unique ?
        return [NodeBusLink(self, **kwargs) for kwargs in cursor]

    def get_node_message(self, node: Node, name: str) -> model.Message:
        nodes = self.get_messages_with_filter(name=name, node_id=node.id)

        if len(nodes) != 0:
            assert len(nodes) == 1
            node, = nodes
            return node

        raise Exception(f'No message named "{name}" found in node {node.fully_qualified_name}')

    def get_nodes(self, scope: Optional[Package] = None) -> Iterable[Node]:
        if scope is not None:
            return self.get_nodes_with_filter(package_id=scope.id)
        else:
            if self.all_units is None:
                self.all_units = self.get_nodes_with_filter()

            return self.all_units

    def get_node_message_links(self, message: Optional[Message] = None, node: Optional[Node] = None
                               ) -> Iterable[model.NodeMessageLink]:
        # TODO: caching

        if node is not None:
            query, args = build_filter('SELECT operation AS link_type, message_id FROM message_node WHERE 1 = 1',
                                       dict(message_id=message.id if message else None, node_id=node.id if node else None))
            cursor = self._make_cursor(dictionary=True)
            cursor.execute(query, args)

            # FIXME: probably cannot do this -- database-linked objects should be unique ?
            # Also super inefficient (another fetch for every message!)
            return set(model.NodeMessageLink(node=node,
                                             message=self.get_message_by_id(row["message_id"]),
                                             link_type=model.NodeMessageLinkType[row["link_type"]]
                                             ) for row in cursor)
        elif message is not None:
            query, args = build_filter('SELECT operation AS link_type, node.description, node.id, node.name, node.package_id '
                                       'FROM message_node JOIN node ON message_node.node_id = node.id',
                                       dict(message_id=message.id))
            cursor = self._make_cursor(buffered=True, dictionary=True)
            cursor.execute(query, args)

            results = set()

            for row in cursor:
                link_type=model.NodeMessageLinkType[row["link_type"]]
                del row["link_type"]

                node = self.get_unit_by_id(row['id'], db_row=row)

                # FIXME: probably cannot do this -- database-linked objects should be unique ?
                results.add(model.NodeMessageLink(node=node,
                                                  message=message,
                                                  link_type=link_type))

            return results
        else:
            raise NotImplementedError()

    def get_nodes_with_filter(self, name: Optional[str] = None, package_id: Optional[int] = None) -> Iterable[Node]:
        nodes = set()

        query, args = build_filter('SELECT id, description, package_id, name FROM node WHERE valid = 1',
                                   dict(name=name, package_id=package_id))
        cursor = self._make_cursor(dictionary=True)
        cursor.execute(query, args)

        for row in cursor:
            nodes.add(self.get_unit_by_id(row['id'], db_row=row))

        return nodes

    def get_package(self, name: str) -> Package:
        if name not in self.packages_by_name:
            cursor = self._make_cursor(dictionary=True)
            cursor.execute('SELECT id, name FROM package WHERE name = %s', (name,))

            package = Package(self, **cursor.fetchone())
            self.packages_by_id[package.id] = package
            self.packages_by_name[package.name] = package

        return self.packages_by_name[name]

    def get_package_by_id(self, package_id: int, db_row=None) -> Package:
        if package_id not in self.packages_by_id:
            if db_row is None:
                cursor = self._make_cursor(dictionary=True)
                cursor.execute('SELECT id, name FROM package WHERE id = %s', (package_id,))
                db_row = cursor.fetchone()

            if db_row is None:
                raise Exception(f'Cannot find Package(id={package_id})')

            package = Package(self, **db_row)
            self.packages_by_id[package.id] = package
            self.packages_by_name[package.name] = package

        return self.packages_by_id[package_id]

    def get_package_node(self, package: Package, name: str) -> model.Node:
        nodes = self.get_nodes_with_filter(name=name, package_id=package.id)

        if len(nodes) != 0:
            assert len(nodes) == 1
            node, = nodes
            return node

        raise Exception(f'No node named "{name}" found in package {package.fully_qualified_name}')

    def get_packages(self) -> Iterable[model.Package]:
        if not self.have_all_packages:
            self._get_packages()
            self.have_all_packages = True

        return self.packages_by_id.values()

    def get_unit_by_id(self, unit_id: int, db_row=None) -> Node:
        if unit_id not in self.units_by_id:
            if db_row is None:
                cursor = self._make_cursor(dictionary=True)
                cursor.execute('SELECT id, description, package_id, name FROM node WHERE id = %s AND valid = 1',
                               (unit_id,))

                db_row = cursor.fetchone()

            if db_row is None:
                raise Exception(f'Cannot find Node(id={unit_id})')

            self.units_by_id[unit_id] = Node(self, **db_row)

        return self.units_by_id[unit_id]

    # TODO: make private
    def load_message_fields(self, message: Message) -> Iterable[MessageField]:
        # cursor must be buffered (= fetched all at once) because MessageField's constructor may need to perform
        # another query to resolve the fully-qualified name
        cursor = self._make_cursor(dictionary=True, buffered=True)
        cursor.execute('SELECT bit_size AS size_in_bits, name, description, type, array_length, unit, offset, factor, min, max '
                       'FROM message_field WHERE message_id = %s AND valid = 1 ORDER BY position',
                       (message.id,))

        fields = [] # strictly speaking, it should be an ordered set

        for kwargs in cursor:
            kwargs["type"] = self.resolve_type(kwargs["type"])
            fields.append(MessageField(message, **kwargs))

        return fields

    def resolve_type(self, type_name_or_enum_id: str) -> model.MessageFieldType:
        if type_name_or_enum_id in model.MESSAGE_FIELD_PRIMITIVE_TYPES:
            return model.MESSAGE_FIELD_PRIMITIVE_TYPES[type_name_or_enum_id]
        else:
            enum_type_id = int(type_name_or_enum_id)

            enum_type = self.get_enum_type_by_id(enum_type_id)

            # node = self.get_package_node(package, node_name)
            return model.EnumMessageFieldType(node=enum_type.node, enum=enum_type.name,
                                              fully_qualified_name=enum_type.fully_qualified_name)

    def run_consistency_checks(self) -> None:
        fixes = set()

        # Check for messages belonging to invalidated nodes
        cursor = self._make_cursor(dictionary=True)
        cursor.execute('SELECT message.id AS message_id, node.id AS unit_id FROM message '
                       'LEFT JOIN node ON message.node_id = node.id '
                       'WHERE message.valid = 1 AND node.valid = 0')

        for row in cursor:
            print(f"warning: consistency violation: Message(id={row['message_id']}) belongs to deleted "
                  f"Node(id={row['unit_id']})", file=sys.stderr)

            fixes.add(
                'UPDATE message LEFT JOIN node ON message.node_id = node.id SET message.valid = 0 '
                'WHERE message.valid = 1 AND node.valid = 0')

        for fix in fixes:
            print(f'note: recommended fix "{fix}"', file=sys.stderr)

    def transaction_begin(self) -> None:
        if self.conn.in_transaction:
            # unclear why we are in a transaction by default
            self.conn.rollback()

        self.conn.start_transaction()

    def transaction_commit(self) -> None:
        self.conn.commit()

    #
    # private functions
    #

    def _delete_message_by_id(self, message_id: int, who_changed: str) -> None:
        cursor = self._make_cursor(dictionary=True)
        cursor.execute('UPDATE message SET valid = 0 WHERE message.id = %s', (message_id,))

        if cursor.rowcount <= 0:
            raise Exception("No such message")

        cursor.execute('DELETE FROM message_node WHERE message_id = %s', (message_id,))
        self._put_changelog_entry(ChangelogEntityType.MESSAGE, ChangelogAction.DELETE, message_id, who_changed)

        # FIXME: purge cache (or wait until commit ?)

    def _delete_node_by_id(self, node_id: int, who_changed: str) -> None:
        cursor = self._make_cursor(dictionary=True)
        cursor.execute('UPDATE node SET valid = 0 WHERE node.id = %s AND node.valid = 1', (node_id,))

        if cursor.rowcount <= 0:
            raise Exception("No such node")

        cursor.execute('UPDATE message SET valid = 0 WHERE message.node_id = %s', (node_id,))

        self._put_changelog_entry(ChangelogEntityType.NODE, ChangelogAction.DELETE, node_id, who_changed)

        # FIXME: purge cache (or wait until commit ?)

    def _get_packages(self) -> Iterable[Package]:
        packages = set()

        cursor = self._make_cursor(dictionary=True)
        cursor.execute('SELECT id, name FROM package')

        for row in cursor:
            packages.add(self.get_package_by_id(row['id'], db_row=row))

        return packages

    def _make_cursor(self, *args, **kwargs):
        try:
            return self.conn.cursor(*args, **kwargs)
        except mysql.connector.errors.OperationalError as ex:
            if "MySQL Connection not available." in ex.msg:
                # connection to MySQL DB has been lost. re-connect and try again
                self.conn = mysql.connector.connect(**self._connection_kwargs)
                return self.conn.cursor(*args, **kwargs)
            else:
                raise

    def _put_changelog_entry(self, entity_type: ChangelogEntityType, action: ChangelogAction, row_id: int, who_changed: str):
        table_by_entity_type = {
            ChangelogEntityType.MESSAGE: "message",
            ChangelogEntityType.NODE: "node",
        }

        cursor = self._make_cursor()
        cursor.execute("INSERT INTO changelog (`table`, `action`, `row`, `who_changed`) VALUES (%s, %s, %s, %s)",
                       (table_by_entity_type[entity_type], action.name, row_id, who_changed))
