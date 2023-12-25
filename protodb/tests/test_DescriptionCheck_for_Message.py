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

import yaml

from protodb.drc.all_checks import DescriptionCheck
from protodb.jsonprotocoldatabase import JsonProtocolDatabase
from protodb.tests.testutil import check_over_set

MODEL = yaml.safe_load('''
version: 2
packages:
- name: A
  buses:
  - name: Bus
  units:
  - name: B
    description: A
    bus_links: []
    enum_types: []
    messages:
    - name: Empty
      description: null
      bus: A.Bus
      fields: []
      timeout: 1
      tx_period: 1
    - name: Terminate_C_comment
      description: "a*/b"
      bus: A.Bus
      fields: []
      timeout: 1
      tx_period: 1
    - name: Non_ASCII_character
      description: "a\u00a0b"
      bus: A.Bus
      fields: []
      timeout: 1
      tx_period: 1
    - name: Perfectly_valid
      description: |
        Multi-line
        description
        is fine.
      bus: A.Bus
      fields: []
      timeout: 1
      tx_period: 1
''')


def test_DescriptionCheck_for_Message(db=JsonProtocolDatabase.from_model(MODEL)):
    violations = check_over_set(db, DescriptionCheck(), db.get_messages())
    assert ('DescriptionMissing', 'object=Message(A.B.Empty)') in violations
    assert ('BadCharactersInDescription', 'object=Message(A.B.Terminate_C_comment)') in violations
    assert ('BadCharactersInDescription', 'object=Message(A.B.Non_ASCII_character)') in violations
    assert ('BadCharactersInDescription', 'object=Message(A.B.Perfectly_valid)') not in violations
