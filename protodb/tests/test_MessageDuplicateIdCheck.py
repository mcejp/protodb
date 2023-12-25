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

from protodb.drc.all_checks import MessageDuplicateIdCheck
from protodb.jsonprotocoldatabase import JsonProtocolDatabase
from protodb.tests.testutil import check_over_set


MODEL = yaml.safe_load('''
version: 2
packages:
- name: A
  buses:
  - name: Bus
  units:
  - name: Node
    description: A
    bus_links: []
    enum_types: []
    messages:
    - name: Msg1
      description: A
      bus: A.Bus
      fields: []
      id: 352
      length: 1
      timeout: 1
      tx_period: 1
    - name: Msg2
      description: A
      bus: A.Bus
      fields: []
      id: 352
      length: 1
      received_by: []
      timeout: 1
      tx_period: 1
''')


def test_MessageDuplicateIdCheck(db=JsonProtocolDatabase.from_model(MODEL)):
    assert ('CanIdConflict', 'bus=Bus(A.Bus)', 'message=Message(A.Node.Msg1)', 'message2=Message(A.Node.Msg2)') in check_over_set(db, MessageDuplicateIdCheck(), db.get_buses())
