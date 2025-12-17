#!/bin/bash
sudo -u postgres psql << EOF
CREATE USER obsolio_app WITH PASSWORD 'ObsolioApp@2025Secure';
ALTER USER obsolio_app WITH PASSWORD 'ObsolioApp@2025Secure';
GRANT ALL PRIVILEGES ON DATABASE obsolio_db TO obsolio_app;
\c obsolio_db
GRANT ALL ON SCHEMA public TO obsolio_app;
GRANT ALL ON ALL TABLES IN SCHEMA public TO obsolio_app;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO obsolio_app;
EOF
