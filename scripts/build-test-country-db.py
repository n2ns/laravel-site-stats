# Builds tests/fixtures/country-test.mmdb, the small country database of the tests (documentation addresses only).
#   python3 -m venv /tmp/mmdb && /tmp/mmdb/bin/pip install mmdb-writer netaddr
#   /tmp/mmdb/bin/python scripts/build-test-country-db.py
from mmdb_writer import MMDBWriter
from netaddr import IPSet

writer = MMDBWriter(ip_version=6, ipv4_compatible=True, database_type='Test-Country', description={'en': 'N2N Stats test country database'})
writer.insert_network(IPSet(['198.51.100.0/24']), {'country': {'iso_code': 'FR'}})
writer.insert_network(IPSet(['2001:db8::/32']), {'country': {'iso_code': 'JP'}})
writer.insert_network(IPSet(['192.0.2.0/24']), {'continent': {'code': 'EU'}})
writer.to_db_file('tests/fixtures/country-test.mmdb')
