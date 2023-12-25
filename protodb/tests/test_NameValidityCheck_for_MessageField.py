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

from protodb.drc.all_checks import NameValidityCheck
from protodb.jsonprotocoldatabase import JsonProtocolDatabase
from protodb.tests.testutil import check_over_set

MODEL = yaml.safe_load('''
version: 2
packages:
- name: A
  buses:
  - name: CAN2_aux
  units:
  - name: STW
    description: A
    bus_links: []
    enum_types: []
    messages:
    - name: CalibrationReq
      description: A
      bus: A.CAN2_aux
      fields:
      - name: Req ID
        description: A
        type: uint
        bits: 8
        count: 1
      timeout: null
      tx_period: null
''')


def test_NameValidityCheck_for_MessageField(db=JsonProtocolDatabase.from_model(MODEL)):
    assert ('NameNotValidIdentifier', 'object=MessageField(A.STW.CalibrationReq.Req ID)') in check_over_set(db, NameValidityCheck(), db.get_message_fields())
