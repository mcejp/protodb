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

import json
from pathlib import Path

from protodb.export.export_json2 import export_buses_of_package
from protodb.jsonprotocoldatabase import JsonProtocolDatabase


def test_export_json2():
    with open(Path(__file__).parent / "data" / "TestModel.json") as f:
        model = json.load(f)
        db = JsonProtocolDatabase.from_model(model, strict=False)

        out_model = export_buses_of_package(db.get_package("BCP07"), db)

        # Re-generate
        # with open(Path(__file__).parent / "data" / "test_export_json2.expected.json", "wt") as output:
        #     json.dump(out_model, output, indent=2)

        with open(Path(__file__).parent / "data" / "test_export_json2.expected.json", "rt") as check:
            actual_lines = json.dumps(out_model, indent=2).split("\n")

            for line_expected, line_actual in zip(check, actual_lines):
                assert line_expected.strip() == line_actual.strip()
