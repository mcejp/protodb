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

from abc import abstractmethod
from dataclasses import dataclass
from datetime import timedelta
from enum import auto, Enum
import itertools
from typing import Iterable, Optional


class FrameType(Enum):
    CAN_STD = auto()
    CAN_EXT = auto()


class Entity:
    pass


class Package(Entity):
    name: str
    fully_qualified_name: str

    def __repr__(self):
        return f'Package({self.fully_qualified_name})'


class Bus(Entity):
    name: str
    fully_qualified_name: str
    dbc_id: Optional[int]
    bitrate: Optional[int]

    package: Package

    def __repr__(self):
        return f'Bus({self.fully_qualified_name})'


class Node(Entity):
    name: str
    description: Optional[str]
    fully_qualified_name: str

    package: Package

    def __repr__(self):
        return f'Node({self.fully_qualified_name})'


@dataclass(eq=False)
class NodeBusLink:
    node: Node
    bus: Bus
    note: str


class Message(Entity):
    name: str
    description: str
    fully_qualified_name: str
    frame_type: FrameType
    can_id: Optional[int]
    timeout: Optional[timedelta]
    tx_period: Optional[timedelta]

    bus: Bus
    fields: Iterable['MessageField']
    unit: Node

    def __repr__(self):
        return f'Message({self.fully_qualified_name})'


class MessageFieldType:
    pass


@dataclass(eq=False)
class BasicMessageFieldType(MessageFieldType):
    type_name: str

    def __repr__(self):
        return self.type_name


@dataclass(eq=False)
class EnumMessageFieldType(MessageFieldType):
    node: Node
    enum: str

    fully_qualified_name: str

    def __repr__(self):
        return f"enum {self.fully_qualified_name}"


MESSAGE_FIELD_TYPE_BOOL =       BasicMessageFieldType("bool")
MESSAGE_FIELD_TYPE_FLOAT =      BasicMessageFieldType("float")
MESSAGE_FIELD_TYPE_INT =        BasicMessageFieldType("int")
MESSAGE_FIELD_TYPE_MULTIPLEX =  BasicMessageFieldType("multiplex")
MESSAGE_FIELD_TYPE_RESERVED =   BasicMessageFieldType("reserved")
MESSAGE_FIELD_TYPE_UINT =       BasicMessageFieldType("uint")

MESSAGE_FIELD_PRIMITIVE_TYPES = {
    "bool":         MESSAGE_FIELD_TYPE_BOOL,
    "float":        MESSAGE_FIELD_TYPE_FLOAT,
    "int":          MESSAGE_FIELD_TYPE_INT,
    "multiplex":    MESSAGE_FIELD_TYPE_MULTIPLEX,
    "reserved":     MESSAGE_FIELD_TYPE_RESERVED,
    "uint":         MESSAGE_FIELD_TYPE_UINT,
}


@dataclass(eq=False)
class MessageField(Entity):
    name: str
    description: str

    type: MessageFieldType
    size_in_bits: int       # TODO: long-term, shouldn't this be part of Type ?
    array_length: int       # TODO: long-term, shouldn't this be part of Type ?

    unit: Optional[str] = None
    factor: Optional[str] = None
    offset: Optional[str] = None
    min: Optional[str] = None
    max: Optional[str] = None

    message: Message = None
    fully_qualified_name: Optional[str] = None

    def __repr__(self):
        return f'MessageField({self.fully_qualified_name})'

    @property
    def holds_value(self):
        # TODO: probably there should be a lower-level constraint where empty name implies a reserved type
        # For now, we just check for this to avoid triggering
        return len(self.name) and self.type is not MESSAGE_FIELD_TYPE_RESERVED


class NodeMessageLinkType(Enum):
    SENDER = auto()
    RECEIVER = auto()


@dataclass(eq=False)
class NodeMessageLink:
    node: Node
    message: Message
    link_type: NodeMessageLinkType


class EnumType:
    name: str
    description: str

    items: Iterable['EnumTypeItem']

    node: Node
    fully_qualified_name: str


@dataclass(eq=False)
class EnumTypeItem:
    name: str
    description: str
    value: int
