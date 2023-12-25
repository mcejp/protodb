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
from typing import Set

from .. import ProtocolDatabase
from ..model import (
    Bus,
    EnumMessageFieldType,
    Message,
    Node,
    NodeMessageLinkType,
    Package,
    MESSAGE_FIELD_TYPE_RESERVED,
)


FIXUP_DETECT_RESERVED_FIELDS = True
FIXUP_EMPTY_STRINGS_TO_NULL = True


class ExportSet:
    messages: Set
    packages: Set
    units: Set  # TODO rename

    _db: ProtocolDatabase

    def __init__(self, db: ProtocolDatabase):
        self.messages = set()
        self.packages = set()
        self.units = set()

        self._db = db

    def add_bus(self, bus: Bus) -> None:
        # iterate all messages associated to bus
        all_messages = self._db.get_associated_messages(bus)

        # add units as necessary
        for message in all_messages:
            self.add_message(message)

        # however, also consider all ECUs linked to this bus and add all of their message that do not have an Associated Bus specified
        # this way, accessory devices can be connected to several different car generations
        # (the ECUs are linked to buses from all the cars, but the messages are left un-associated)
        # TODO: such messages should be flagged more explicitly
        relevant_nodes = self._db.get_bus_nodes(bus)

        for node in relevant_nodes:
            for message in self._db.get_messages(scope=node):
                if message.bus is None:
                    self.add_message(message)

    def add_message(self, message: Message) -> None:
        if message in self.messages:
            return

        # ensure we have the corresponding ECU as well
        self.add_unit_with_enums(message.unit)

        self.messages.add(message)

    def add_package(self, package: Package) -> None:
        if package in self.packages:
            return

        self.packages.add(package)

    # TODO: rename
    def add_unit_with_enums(self, unit: Node) -> None:
        if unit in self.units:
            return

        self.add_package(unit.package)

        self.units.add(unit)


def _compute_message_layout(self):
    """
    Returns (field_ranges, num_bytes)
    Awful, awful interface
    Also doesn't belong here.
    """

    usage_map = []
    field_ranges = []

    for field in self.fields:
        ranges = []
        start_bit = None

        # if isinstance(field.type, ArrayMessageFieldType):
        #     type = field.type.element_type
        #     array_length = field.type.length
        # else:
        #     type = field.type
        #     array_length = 1

        for i in range(field.array_length):
            b = field.size_in_bits
            ranges.append([])

            if b % 8 == 0:
                # multiple of 8 bits - always append to the end

                if start_bit is None:
                    start_bit = len(usage_map) * 8

                for byte in range(b // 8):
                    ranges[i].append((len(usage_map), 0, 8))
                    usage_map.append(8)
            else:
                # try to find a non-full byte first
                for byte, used_bits in enumerate(usage_map):
                    if used_bits == 8:
                        continue

                    if start_bit is None:
                        start_bit = byte * 8 + used_bits

                    # use as many bits as possible in this byte
                    use = min(b, 8 - used_bits)

                    ranges[i].append((byte, used_bits, use))
                    b -= use
                    usage_map[byte] += use

                    if b == 0:
                        break

                if start_bit is None:
                    start_bit = len(usage_map) * 8

                # any bits left over? - put them at the end
                if b != 0:
                    while b > 0:
                        use = 8 if b > 8 else b
                        ranges[i].append((len(usage_map), 0, use))
                        usage_map.append(use)
                        b -= use

        field_ranges.append((start_bit, ranges))

    num_bytes = len(usage_map)
    return field_ranges, num_bytes


def _try_convert_factor_to_float(factor_str):
    try:
        # Opportunistically try to convert it directly
        return float(factor_str)
    except Exception:
        pass

    match = re.fullmatch(r"\(([0-9.]+)/([0-9.]+)\)", factor_str)

    if match:
        try:
            return float(match.group(1)) / float(match.group(2))
        except ZeroDivisionError:
            pass  # ¯\_(ツ)_/¯

    return None


def _get_model_for_message(message: Message, db: ProtocolDatabase):
    if message.bus is not None:
        bus_name = message.bus.fully_qualified_name
    else:
        bus_name = None

    layout, num_bytes = _compute_message_layout(message)

    refs = db.get_node_message_links(message=message)
    sent_by = sorted(ref.node.fully_qualified_name for ref in refs if ref.link_type == NodeMessageLinkType.SENDER)
    received_by = sorted(ref.node.fully_qualified_name for ref in refs if ref.link_type == NodeMessageLinkType.RECEIVER)

    message_model = dict(
        name=message.name,
        description=message.description if message.description else None,
        bus=bus_name,
        fields=[],
        frame_type=message.frame_type.name,
        # FIXME: this must be handled at model level, not here!
        # id=message.can_id if message.can_id_type != Message::CAN_ID_TYPE_UNDEF else None,
        id=message.can_id,
        length=num_bytes,
        received_by=received_by,
        sent_by=sent_by,
        timeout=int(message.timeout.total_seconds() * 1000) if message.timeout is not None else None,
        tx_period=int(message.tx_period.total_seconds() * 1000) if message.tx_period is not None else None,
    )

    for field, field_layout in zip(message.fields, layout):
        if field.type is MESSAGE_FIELD_TYPE_RESERVED:
            name = None
            type = repr(field.type)
        elif FIXUP_DETECT_RESERVED_FIELDS and not field.name:
            name = None
            type = repr(MESSAGE_FIELD_TYPE_RESERVED)
        elif isinstance(field.type, EnumMessageFieldType):
            # for backwards compatibility, we need "enum CHG_AccumulatorState"
            # rather than                          "enum Accessory.CHG.AccumulatorState"
            name = field.name
            type = f"enum {field.type.node.name}_{field.type.enum}"
        else:
            name = field.name
            type = repr(field.type)

        # TODO: this _definitely_ doesn't belong here
        if field.factor:
            factor_num = _try_convert_factor_to_float(field.factor)
        else:
            factor_num = None

        start_bit, ranges = field_layout

        if FIXUP_EMPTY_STRINGS_TO_NULL:
            unit = field.unit if field.unit else None
            factor = field.factor if field.factor else None
            offset = field.offset if field.offset else None
            min = field.min if field.min else None
            max = field.max if field.max else None
        else:
            unit = field.unit
            factor = field.factor
            offset = field.offset
            min = field.min
            max = field.max

        message_field_model = dict(
            name=name,
            description=field.description if field.description else None,
            type=type,
            bits=field.size_in_bits,
            count=field.array_length,
            start_bit=start_bit,  # how can there even be a single value describing an array of fields?
            unit=unit,
            factor=factor,
            factor_num=factor_num,
            offset=offset,
            min=min,
            max=max,
        )

        message_model["fields"].append(message_field_model)

    return message_model


def _render_set(set: ExportSet, db: ProtocolDatabase):
    model = dict(
        version=2,
        packages=[],
    )

    for package in sorted(set.packages, key=lambda package: package.name):
        package_model = dict(
            name=package.name,
            buses=[],
            units=[],
        )

        sorted_buses = sorted(db.get_buses(scope=package), key=lambda bus: bus.name)

        for bus in sorted_buses:
            bus_model = dict(dbc_id=bus.dbc_id, name=bus.name, bitrate=bus.bitrate)

            package_model["buses"].append(bus_model)

        sorted_nodes = sorted(db.get_nodes(scope=package), key=lambda node: node.name)

        for node in sorted_nodes:
            if node not in set.units:  # what a mess
                continue

            # assert node.description != ""  # should be None instead
            node_model = dict(
                name=node.name,
                description=node.description,
                bus_links=[],
                enum_types=[],
                messages=[],
            )

            for bus_link in db.get_node_bus_links(node):
                bus_link_model = dict(
                    bus=bus_link.bus.fully_qualified_name,
                    note=bus_link.note if bus_link.note else None,
                )

                node_model["bus_links"].append(bus_link_model)

            sorted_enum_types = sorted(db.get_enum_types(scope=node), key=lambda enum_type: enum_type.name)

            for enum_type in sorted_enum_types:
                # TODO: why not check if is in set?

                assert enum_type.description != ""  # should be None instead
                enum_type_model = dict(
                    name=enum_type.name,
                    description=enum_type.description,
                    items=[],
                )

                for item in sorted(enum_type.items, key=lambda item: item.value):
                    enum_item_model = dict(
                        name=item.name,
                        value=item.value,
                        description=item.description if item.description else None,
                    )
                    enum_type_model["items"].append(enum_item_model)

                node_model["enum_types"].append(enum_type_model)

            sorted_messages = sorted(db.get_messages(scope=node), key=lambda message: message.name)

            for message in sorted_messages:
                if message not in set.messages:
                    continue

                node_model["messages"].append(_get_model_for_message(message, db))

            package_model["units"].append(node_model)

        model["packages"].append(package_model)

    return model


def export_buses_of_package(package: Package, db: ProtocolDatabase):
    export_set = ExportSet(db)

    for bus in db.get_buses(scope=package):
        export_set.add_bus(bus)

    return _render_set(export_set, db)


if __name__ == "__main__":
    import json
    import sys

    import configargparse

    from .. import connect

    parser = configargparse.ArgParser()
    parser.add_argument('--db', dest='conn_string', env_var='PROTODB_CONN_STRING', required=True)
    parser.add_argument("package")
    args = parser.parse_args()

    db = connect(args.conn_string)

    package = db.get_package(args.package)

    model = export_buses_of_package(package, db)

    json.dump(model, sys.stdout)
