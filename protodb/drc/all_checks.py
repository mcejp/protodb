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

import re
import typing
from abc import abstractmethod
from datetime import timedelta

from .. import drc
from ..model import Bus, EnumMessageFieldType, Message, MessageField, Node, Package
from ..protocoldatabase import InvalidScopeError


class BusCheck(drc.Check):
    @abstractmethod
    def __call__(self, ctx: drc.DrcContext, bus: Bus) -> None:
        pass


class MessageCheck(drc.Check):
    @abstractmethod
    def __call__(self, ctx: drc.DrcContext, message: Message) -> None:
        pass


class MessageFieldCheck(drc.Check):
    @abstractmethod
    def __call__(self, ctx: drc.DrcContext, field: MessageField) -> None:
        pass


class NodeCheck(drc.Check):
    @abstractmethod
    def __call__(self, ctx: drc.DrcContext, unit: Node) -> None:
        pass


MESSAGE_FIELD_TOO_SMALL = "MessageFieldTooSmall"
MESSAGE_FIELD_UNIT_UNSPECIFIED = "MessageFieldUnitUnspecified"


class DescriptionCheck(MessageCheck, MessageFieldCheck, NodeCheck):
    re: typing.Pattern

    def __init__(self):
        self.re = re.compile('^[\x09\x0a\x0d\x20-\x7f]*$')

    def __call__(self, ctx: drc.DrcContext, object) -> None:
        # All named objects should have a description
        # Example of un-named object: a reserved message field
        if object.name and not object.description:
            ctx.violation("DescriptionMissing", object=object)

        # Warn also about "dangerous" characters
        if object.description and ('*/' in object.description or not self.re.fullmatch(object.description)):
            ctx.violation("BadCharactersInDescription", object=object)


class MessageBusNotEmpty(MessageCheck):
    def __call__(self, ctx: drc.DrcContext, message: Message) -> None:
        if message.bus is None:
            ctx.violation("BusUnspecified", message=message)


class MessageBusValidCheck(MessageCheck):
    def __call__(self, ctx: drc.DrcContext, message: Message) -> None:
        valid_buses = [bus_link.bus for bus_link in db.get_node_bus_links(message.unit)]

        # If an Associated Bus is specified, it must be one belonging to the ECU
        if message.bus is not None and message.bus not in valid_buses:
            ctx.violation("BusInvalid", message=message, bus=message.bus)


class MessageDuplicateIdCheck(BusCheck):
    def __call__(self, ctx: drc.DrcContext, bus: Bus) -> None:
        messages_by_id = {}

        for message in ctx.db.get_associated_messages(bus=bus):
            if message.can_id is None:
                continue

            if message.can_id in messages_by_id:
                ctx.violation("CanIdConflict", bus=bus, message=messages_by_id[message.can_id], message2=message)
            else:
                messages_by_id[message.can_id] = message


class MessageFieldPropertiesCheck(MessageFieldCheck):
    def __call__(self, ctx: drc.DrcContext, field: MessageField) -> None:
        if field.holds_value and field.unit is None:
            ctx.violation(MESSAGE_FIELD_UNIT_UNSPECIFIED, field=field)

        # TODO: only applies to certain types of fields
        # if field.factor is None or field.offset is None or field.min is None or field.max is None:
        #     ctx.violation(MESSAGE_FIELD_MAPPING_UNSPECIFIED, field=field)


class MessageFieldSizeCheck(MessageFieldCheck):
    def __call__(self, ctx: drc.DrcContext, field: MessageField) -> None:
        if isinstance(field.type, EnumMessageFieldType):
            enum = ctx.db.get_enum_type(field.type)

            max_value = max([item.value for item in enum.items])

            if max_value >= (1 << field.size_in_bits):
                ctx.violation(MESSAGE_FIELD_TOO_SMALL, field=field)

        # TODO: check bit_size also against factor/offset/min/max


class MessagePropertiesCheck(MessageCheck):
    def __call__(self, ctx: drc.DrcContext, message: Message) -> None:
        if message.timeout == timedelta(0):
            ctx.violation("MessageTimeoutZero", message=message)

        if message.tx_period == timedelta(0):
            ctx.violation("MessageTxPeriodZero", message=message)


class NameValidityCheck(BusCheck, MessageCheck, MessageFieldCheck, NodeCheck):
    re: typing.Pattern

    def __init__(self):
        self.re = re.compile('^[a-zA-Z][a-zA-Z0-9_]*$')

    def __call__(self, ctx: drc.DrcContext, object) -> None:
        if object.name and not self.re.fullmatch(object.name):
            ctx.violation("NameNotValidIdentifier", object=object)


__all__ = [
    DescriptionCheck(),
    MessageBusNotEmpty(),
    MessageBusValidCheck(),
    MessageDuplicateIdCheck(),
    MessageFieldPropertiesCheck(),
    MessageFieldSizeCheck(),
    MessagePropertiesCheck(),
    NameValidityCheck(),
]

if __name__ == '__main__':
    import configargparse
    import sys

    from . import ProtocolDatabase
    from .. import connect


    def parse_scope(db: ProtocolDatabase, scope_str: str):
        if scope_str is None:
            return db

        key, value = scope_str.split('=')

        if key == 'package':
            return db.get_package(value)
        elif key == "node" or key == 'unit':
            return db.get_node(value)
        else:
            raise Exception(f'Invalid scope string "{scope_str}"')

    def run_for_all_in(check, set: typing.Iterable, verbose: bool):
        for object in set:
            if verbose:
                print(f'Running check {check} on {object}')

            check(context, object)

    def get_buses_in_scope(db, scope) -> typing.Iterable[Bus]:
        if isinstance(scope, Bus):
            return [scope]
        elif isinstance(scope, Package):
            return db.get_buses(scope=scope)
        else:
            return []

    def get_nodes_in_scope(db, scope) -> typing.Iterable[Node]:
        if isinstance(scope, Node):
            return [scope]
        else:
            try:
                return db.get_nodes(scope=scope)
            except InvalidScopeError:
                return []

    def get_messages_in_scope(db, scope) -> typing.Iterable[Message]:
        if isinstance(scope, Message):
            return [scope]
        else:
            try:
                return db.get_messages(scope=scope)
            except InvalidScopeError:
                return []

    def get_message_fields_in_scope(db, scope) -> typing.Iterable[MessageField]:
        if isinstance(scope, MessageField):
            return [scope]
        else:
            try:
                return db.get_message_fields(scope=scope)
            except InvalidScopeError:
                return []

    parser = configargparse.ArgParser()
    parser.add_argument('--db', dest='conn_string', env_var='PROTODB_CONN_STRING', required=True)
    parser.add_argument('--scope')
    parser.add_argument('-v', dest='verbose', action='store_true')
    args = parser.parse_args()

    db = connect(args.conn_string)
    scope = parse_scope(db, args.scope)

    output = drc.DrcOutput()
    context = drc.DrcContext(db, output)

    for check in __all__:
        handled = False

        if isinstance(check, BusCheck):
            run_for_all_in(check, get_buses_in_scope(db, scope), args.verbose)
            handled = True

        if isinstance(check, MessageCheck):
            run_for_all_in(check, get_messages_in_scope(db, scope), args.verbose)
            handled = True

        if isinstance(check, MessageFieldCheck):
            run_for_all_in(check, get_message_fields_in_scope(db, scope), args.verbose)
            handled = True

        if isinstance(check, NodeCheck) and hasattr(scope, 'get_nodes'):
            run_for_all_in(check, get_nodes_in_scope(db, scope), args.verbose)
            handled = True

        if not handled:
            print(f'warning: Don\'t know how to apply check {check}', file=sys.stderr)
