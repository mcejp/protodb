# protodb

Note: Python 3.8+ is required!

Entity hierarchy:

- Package, a.k.a. an entire vehicle
    - Bus
    - Node, or Unit (in web UI & JSON encoding), a.k.a. ECU
        - Message, a type of CAN frame with unique ID within the bus
            - Message field

- DRC incident

# Testing

Unit tests:

    python -m pytest

in ProtoDB root.

Full-DB tests:

    python -mprotodb.drc.all_checks\
           --scope=package=D1\
           --db D1.json

With MySQL running and DB imported:

    python -mprotodb.drc.all_checks\
           --scope=package=D1\
           --db mysql:host=localhost;port=3306;dbname=candb;charset=utf8mb4;user=candb;password=password;collation=utf8mb4_general_ci

Unit tests with MySQL are yet to be figured out. (This is a big problem for production!)

### Using ProtoDB commands on server

    docker exec -it candbdev_php_1 sh
    python3 -m protodb.delete_node D1.PDL --db $PROTODB_PDO_DSN\;user=$PROTODB_PDO_USER\;password=$PROTODB_PDO_PASSWORD

### Using minimize_json_testcase

- Prepare a "big", but correct, test-case
- Run the minimizer, for example:

        python -m protodb.minimize_json_testcase \
               protodb.tests.test_NameValidityCheck_for_MessageField \
               test_NameValidityCheck_for_MessageField \
               <input.json>

- By the end, the test case will have been reduced to the minimum.
  The output is saved to a new file.
