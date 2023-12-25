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

from abc import ABC, abstractmethod
from typing import Iterable, Optional

from . import model


class InvalidScopeError(BaseException):
    pass


class ProtocolDatabase(ABC):
    @abstractmethod
    def delete(self, entity: model.Entity, who_changed: str) -> None:
        pass

    @abstractmethod
    def get_associated_messages(self, bus: model.Bus) -> Iterable[model.Message]:
        pass

    @abstractmethod
    def get_buses(self, scope: Optional[model.Package] = None) -> Iterable[model.Bus]:
        pass

    @abstractmethod
    def get_bus_nodes(self, bus: model.Bus) -> Iterable[model.Node]:
        pass

    @abstractmethod
    def get_enum_type(self, type: model.EnumMessageFieldType):
        pass

    @abstractmethod
    def get_enum_types(self, scope: Optional[model.Entity] = None) -> Iterable[model.EnumType]:
        pass

    def get_message(self, /, fully_qualified_name: str) -> model.Message:
        package_name, node_name, message_name = fully_qualified_name.split('.')

        package = self.get_package(package_name)
        node = self.get_package_node(package, node_name)
        return self.get_node_message(node, message_name)

    @abstractmethod
    def get_messages(self, scope: Optional[model.Entity] = None) -> Iterable[model.Message]:
        pass

    @abstractmethod
    def get_message_fields(self, scope: Optional[model.Entity] = None) -> Iterable[model.MessageField]:
        pass

    # TODO: overly general API, refactor
    @abstractmethod
    def get_node_message_links(self, message: Optional[model.Message] = None, node: Optional[model.Node] = None
                               ) -> Iterable[model.NodeMessageLink]:
        pass

    @abstractmethod
    def get_package(self, fully_qualified_name: str) -> model.Package:
        pass

    @abstractmethod
    def get_package_node(self, package: model.Package, name: str) -> model.Node:
        pass

    @abstractmethod
    def get_packages(self) -> Iterable[model.Package]:
        pass

    def get_node(self, /, fully_qualified_name: str) -> model.Node:
        package_name, unit_name = fully_qualified_name.split('.')

        package = self.get_package(package_name)
        return self.get_package_node(package, unit_name)

    @abstractmethod
    def get_node_bus_links(self, node: model.Node) -> Iterable[model.NodeBusLink]:
        pass

    @abstractmethod
    def get_node_message(self, node: model.Node, name: str) -> model.Message:
        pass

    @abstractmethod
    def get_nodes(self, scope: Optional[model.Package] = None) -> Iterable[model.Node]:
        pass
