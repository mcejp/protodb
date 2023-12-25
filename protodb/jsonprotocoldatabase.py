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

import itertools
import json
from datetime import timedelta
from typing import Iterable, Set, Dict, Optional, List

from . import model
from . import ProtocolDatabase
from .model import Entity, EnumTypeItem, FrameType, NodeMessageLink, NodeMessageLinkType
from .protocoldatabase import InvalidScopeError


class Package(model.Package):
    buses_by_name: Dict[str, 'Bus']
    nodes_by_name: Dict[str, 'Node']

    def __init__(self, name: str):
        self.name = name

        self.fully_qualified_name = name

    def get_bus(self, name: str) -> 'Bus':
        return self.buses_by_name[name]

    def get_buses(self) -> Iterable['Bus']:
        return self.buses_by_name.values()

    def get_node(self, name: str) -> 'Node':
        return self.nodes_by_name[name]

    def get_nodes(self) -> Iterable['Node']:
        return self.nodes_by_name.values()


class Bus(model.Bus):
    node_links: Set['NodeBusLink']

    def __init__(self, package: Package, name: str, dbc_id: Optional[int] = None, bitrate: Optional[int] = None):
        self.name = name
        self.dbc_id = dbc_id
        self.bitrate = bitrate

        self.package = package
        self.fully_qualified_name = f'{package.fully_qualified_name}.{name}'


class Node(model.Node):
    enum_types_by_name: Dict[str, 'EnumType']
    messages_by_name: Dict[str, 'Message']

    bus_links: List['NodeBusLink']
    message_links: Set[model.NodeMessageLink]

    def __init__(self, package: Package, name: str, description: Optional[str]):
        self.name = name
        self.description = description

        self.package = package
        self.fully_qualified_name = f'{package.name}.{name}'

    def get_enum_type(self, name: str):
        return self.enum_types_by_name[name]

    def get_enum_types(self):
        return self.enum_types_by_name.values()

    def get_message(self, name: str):
        return self.messages_by_name[name]

    def get_messages(self) -> Iterable['Message']:
        return self.messages_by_name.values()


class NodeBusLink(model.NodeBusLink):
    pass


class Message(model.Message):
    node_links: Iterable[model.NodeMessageLink]

    def __init__(self, unit: Node, name: str, description: str, frame_type: model.FrameType, can_id: Optional[int],
                 timeout: Optional[timedelta], tx_period: Optional[timedelta]):
        self.name = name
        self.description = description
        #self.bus = initialized in 2nd pass
        self.frame_type = frame_type
        self.can_id = can_id
        self.timeout = timeout
        self.tx_period = tx_period

        self.unit = unit
        self.fully_qualified_name = f'{unit.fully_qualified_name}.{name}'


class MessageField(model.MessageField):
    pass


class EnumType(model.EnumType):
    def __init__(self, name: str, description: str, node: Node):
        self.name = name
        self.description = description

        self.node = node
        self.fully_qualified_name = f'{node.fully_qualified_name}.{name}'


class JsonProtocolDatabase(ProtocolDatabase):
    messages: Set[Message]
    message_fields: Set[MessageField]
    packages: Dict[str, Package]
    nodes: Set[Node]

    def __init__(self, /, __do_not_use_directly__):
        self.messages = set()
        self.message_fields = set()
        self.packages = {}
        self.nodes = set()

    @staticmethod
    def from_model(model, strict: bool=False):
        """

        :param model:
        :param strict: if True, strict mode is activated. This means:
                - all referenced buses must be defined
                - Bus.bitrate, Bus.dbc_id are required
                - Message.frame_type, Message.received_by, Message.sent_by are required
        :return:
        """

        self = JsonProtocolDatabase(__do_not_use_directly__=True)

        assert model["version"] == 2

        for package_model in model['packages']:
            package = Package(package_model['name'])

            buses = set()

            for bus_model in package_model['buses']:
                if not strict:
                    dbc_id = bus_model.get("dbc_id", None)
                    bitrate = bus_model.get("bitrate", None)
                else:
                    dbc_id = bus_model["dbc_id"]
                    bitrate = bus_model["bitrate"]

                bus = Bus(package=package, name=bus_model['name'], dbc_id=dbc_id, bitrate=bitrate)
                bus.node_links = set() # to be filled in a later pass
                buses.add(bus)

            nodes = []

            for unit_model in package_model['units']:
                unit = Node(package, unit_model['name'], unit_model['description'])
                unit.message_links = set() # to be filled in a later pass

                enum_types_by_name = {}
                messages = []

                for enum_type_model in unit_model["enum_types"]:
                    enum_type = EnumType(enum_type_model["name"], enum_type_model["description"], unit)

                    items = []

                    for item in enum_type_model["items"]:
                        items.append(EnumTypeItem(item["name"], item["description"], item["value"]))

                    enum_type.items = items
                    enum_types_by_name[enum_type.name] = enum_type

                unit.enum_types_by_name = enum_types_by_name

                for message_model in unit_model['messages']:
                    if not strict and "frame_type" not in message_model:
                        frame_type = FrameType.CAN_STD
                    else:
                        frame_type = FrameType[message_model["frame_type"]]

                    message = Message(unit, message_model['name'], message_model['description'],
                                      frame_type=frame_type,
                                      can_id=message_model['id'] if 'id' in message_model else None,
                                      timeout=timedelta(milliseconds=message_model['timeout']) if message_model['timeout'] is not None else None,
                                      tx_period=timedelta(milliseconds=message_model['tx_period']) if message_model['tx_period'] is not None else None,)

                    message.node_links = [] # to be filled in a later pass

                    fields = []

                    for field_model in message_model['fields']:
                        fqn = f'{message.fully_qualified_name}.{field_model["name"]}'
                        field = MessageField(name=field_model['name'],
                                             description=field_model['description'],
                                             type=None,
                                             size_in_bits=field_model["bits"],
                                             array_length=field_model["count"],

                                             unit=field_model["unit"] if "unit" in field_model else None,
                                             factor=field_model["factor"] if "factor" in field_model else None,
                                             offset=field_model["offset"] if "offset" in field_model else None,
                                             min=field_model["min"] if "min" in field_model else None,
                                             max=field_model["max"] if "max" in field_model else None,

                                             message=message,
                                             fully_qualified_name=fqn,
                                             )
                        fields.append(field)
                        self.message_fields.add(field)

                    message.fields = fields
                    messages.append(message)
                    self.messages.add(message)

                unit.enum_types_by_name = enum_types_by_name
                unit.messages_by_name = {message.name: message for message in messages}

                self.nodes.add(unit)
                nodes.append(unit)

            package.buses_by_name = {bus.name: bus for bus in buses}
            package.nodes_by_name = {unit.name: unit for unit in nodes}

            self.packages[package.name] = package

        # Second pass: resolve
        #  - enum types
        #  - message-bus links
        #  - unit-bus links
        for package_model in model['packages']:
            package = self.get_package(package_model['name'])

            for unit_model in package_model['units']:
                unit = package.get_node(unit_model['name'])

                unit.bus_links = []

                for bus_link_model in unit_model['bus_links']:
                    # the JSON2 format permits references to "foreign" buses (buses in undefined packages)
                    try:
                        # we if we encounter this, we ~~create the package~~ set the bus ref to None and hope nobody notices
                        bus = self.get_bus(bus_link_model['bus'])
                    except KeyError:
                        if strict:
                            raise

                        bus = self._create_bus_just_in_time(bus_link_model['bus'])

                    link = NodeBusLink(unit, bus, bus_link_model['note'])
                    unit.bus_links.append(link)
                    bus.node_links.add(link)

                for message_model in unit_model['messages']:
                    message = unit.get_message(message_model['name'])

                    message.bus = self.get_bus(message_model['bus']) if message_model['bus'] is not None else None

                    for i, field_model in enumerate(message_model['fields']):
                        message.fields[i].type = self.resolve_type(package=package, type_str=field_model["type"])

                    if not strict:
                        received_by = message_model.get("received_by", [])
                        sent_by = message_model.get("sent_by", [])
                    else:
                        received_by = message_model["received_by"]
                        sent_by = message_model["sent_by"]

                    for node_fqn in received_by:
                        try:
                            node: Node = self.get_node(node_fqn)
                        except KeyError:
                            if strict:
                                raise

                            node = self._create_node_just_in_time(node_fqn)

                        link = NodeMessageLink(node=node, message=message, link_type=NodeMessageLinkType.RECEIVER)
                        message.node_links.append(link)
                        node.message_links.add(link)

                    for node_fqn in sent_by:
                        try:
                            node: Node = self.get_node(node_fqn)
                        except KeyError:
                            if strict:
                                raise

                            node = self._create_node_just_in_time(node_fqn)

                        link = NodeMessageLink(node=node, message=message, link_type=NodeMessageLinkType.SENDER)
                        message.node_links.append(link)
                        node.message_links.add(link)

        return self

    def delete(self, entity: model.Entity, who_changed: str) -> None:
        raise NotImplementedError()

    def get_associated_messages(self, bus: model.Bus) -> Iterable[model.Message]:
        return [message for message in self.get_messages(scope=bus.package) if message.bus is bus]

    def get_bus(self, path: str) -> Bus:
        package_name, bus_name = path.split('.')

        return self.get_package(package_name).get_bus(bus_name)

    def get_bus_nodes(self, bus: Bus) -> Iterable[model.Node]:
        return [link.node for link in bus.node_links]

    def get_buses(self, scope: Optional[Package] = None) -> Iterable[Bus]:
        if scope is not None:
            return scope.get_buses()
        else:
            return list(itertools.chain(*[package.get_buses() for package in self.packages.values()]))

    def get_enum_type(self, type: model.EnumMessageFieldType):
        node = type.node
        return node.get_enum_type(type.enum)

    def get_enum_types(self, scope: Optional[model.Entity] = None) -> Iterable[model.EnumType]:
        if scope is not None:
            if isinstance(scope, Node):
                return scope.get_enum_types()
            elif isinstance(scope, Package):
                return itertools.chain(*[node.get_enum_types() for node in scope.get_nodes()])
            else:
                raise InvalidScopeError()
        else:
            raise NotImplementedError()

    def get_messages(self, scope: Optional[Entity] = None) -> Iterable[Message]:
        if scope is not None:
            if isinstance(scope, Node):
                return scope.get_messages()
            elif isinstance(scope, Package):
                return itertools.chain(*[node.get_messages() for node in scope.get_nodes()])
            else:
                raise InvalidScopeError()
        else:
            return self.messages

    def get_message_fields(self, scope: Optional[Entity] = None) -> Iterable[MessageField]:
        if scope is not None:
            if isinstance(scope, Message):
                return scope.fields
            elif isinstance(scope, Node) or isinstance(scope, Package):
                messages = self.get_messages(scope=scope)
                return itertools.chain(*[message.fields for message in messages])
            else:
                raise InvalidScopeError()
        else:
            return self.message_fields

    def get_nodes(self, scope: Optional[Package] = None) -> Iterable[Node]:
        if scope is not None:
            return scope.get_nodes()
        else:
            return self.nodes

    def get_node_bus_links(self, node: Node) -> Iterable[model.NodeBusLink]:
        return node.bus_links

    def get_node_message(self, node: Node, name: str) -> model.Message:
        return node.get_message(name)

    def get_node_message_links(self, message: Optional[Message] = None, node: Optional[Node] = None
                               ) -> Iterable[model.NodeMessageLink]:
        if node is not None:
            return node.message_links
        elif message is not None:
            return message.node_links
        else:
            raise NotImplementedError()

    def get_package(self, name: str) -> Package:
        return self.packages[name]

    def get_package_node(self, package: Package, name: str) -> model.Node:
        return package.get_node(name)

    def get_packages(self) -> Iterable[model.Package]:
        return self.packages.values()

    def resolve_type(self, package: model.Package, type_str: str) -> model.MessageFieldType:
        if type_str in model.MESSAGE_FIELD_PRIMITIVE_TYPES:
            return model.MESSAGE_FIELD_PRIMITIVE_TYPES[type_str]
        elif type_str.startswith("enum "):
            node_and_enum = type_str[5:]
            delim = node_and_enum.find(".")

            if delim == -1:
                # Used in JSON version 2
                delim = node_and_enum.find("_")

            assert delim > 0
            node_name = node_and_enum[:delim]
            enum = node_and_enum[delim + 1:]

            node = self.get_package_node(package, node_name)
            return model.EnumMessageFieldType(node=node, enum=enum,
                                              fully_qualified_name=f"{node.fully_qualified_name}.{enum}")
        else:
            raise Exception(f"Invalid type {type_str}")

    @staticmethod
    def with_path(path):
        with open(path, 'rt') as f:
            return JsonProtocolDatabase.from_model(json.load(f))

    def _create_bus_just_in_time(self, fully_qualified_name: str) -> Bus:
        package_name, bus_name = fully_qualified_name.split('.')

        try:
            package = self.get_package(package_name)
        except KeyError:
            package = self._create_package_just_in_time(package_name)

        bus = Bus(name=bus_name, package=package)
        bus.node_links = set()
        package.buses_by_name[bus.name] = bus
        return bus

    def _create_node_just_in_time(self, fully_qualified_name: str) -> Node:
        package_name, node_name = fully_qualified_name.split('.')

        try:
            package = self.get_package(package_name)
        except KeyError:
            package = self._create_package_just_in_time(package_name)

        node = Node(name=node_name, description=None, package=package)
        node.message_links = set()
        package.nodes_by_name[node.name] = node
        return node

    def _create_package_just_in_time(self, name: str) -> Package:
        package = Package(name=name)
        package.buses_by_name = dict()
        package.nodes_by_name = dict()
        self.packages[package.name] = package
        return package
