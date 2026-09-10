import os
import psycopg2

print('=== ENVIRONMENT VARIABLES ===')
print('DB_HOST:', os.getenv('DB_HOST'))
print('DB_DATABASE:', os.getenv('DB_DATABASE'))
print('DB_USERNAME:', os.getenv('DB_USERNAME'))
print()

print('=== CONNECTING TO DATABASE ===')
try:
    conn = psycopg2.connect(
        host=os.getenv('DB_HOST'),
        port=int(os.getenv('DB_PORT', 5432)),
        dbname=os.getenv('DB_DATABASE'),
        user=os.getenv('DB_USERNAME'),
        password=os.getenv('DB_PASSWORD')
    )
    print('✓ Connected successfully!')
    print()
    
    cur = conn.cursor()
    
    print('=== ALL TABLES IN DATABASE ===')
    cur.execute("SELECT table_schema, table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;")
    tables = cur.fetchall()
    for schema, table in tables:
        print(f'  - {schema}.{table}')
    print()
    
    print('=== CHECKING document_chunks ===')
    cur.execute("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'document_chunks');")
    exists = cur.fetchone()[0]
    print(f'  Table exists: {exists}')
    
    if exists:
        print()
        print('=== TABLE STRUCTURE ===')
        cur.execute("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'document_chunks' ORDER BY ordinal_position;")
        columns = cur.fetchall()
        for col_name, col_type in columns:
            print(f'  - {col_name}: {col_type}')
    
    conn.close()
    
except Exception as e:
    print(f'✗ Connection failed: {e}')