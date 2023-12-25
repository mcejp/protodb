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

from abc import ABC

from .. import ProtocolDatabase


class Check(ABC):
    pass


class DrcOutput:
    def __init__(self):
        import csv
        from sys import stdout

        self.csv = csv.writer(stdout, delimiter=' ')

    def emit(self, check, severity, message, details) -> None:
        assert severity is None
        assert message is None

        self.csv.writerow([check] + [f'{key}={value}' for key, value in details.items()])


class DrcOutputCollector:
    def __init__(self):
        self.log = set()

    def emit(self, check, severity, message, details) -> None:
        the_tuple = (str(check), *(f'{key}={value}' for key, value in details.items()))
        self.log.add(the_tuple)

    def get_violations(self):
        return self.log

class DrcContext:
    db: ProtocolDatabase

    def __init__(self, db: ProtocolDatabase, output):
        self.db = db
        self.output = output

    def violation(self, violation_type_name: str, **kwargs) -> None:
        self.output.emit(violation_type_name, None, None, kwargs)
