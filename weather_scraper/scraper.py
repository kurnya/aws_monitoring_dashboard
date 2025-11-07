import pymysql
from contextlib import contextmanager
from datetime import datetime
import time
import traceback
import requests

# ==========================
#   KONFIGURASI DATABASE
# ==========================

DB_HOST = "localhost"
DB_PORT = 3306
DB_USER = "root"
DB_PASS = ""
DB_NAME = "aws_monitoring"           

# ==========================
# KONFIGURASI TARGET SCRAPING
# ==========================
TARGET_URL = "http://202.90.199.132/aws-new/data/station/latest/3000000011"
SCRAPE_INTERVAL = 60  # detik

# ==========================
#      KONEKSI DATABASE
# ==========================
@contextmanager
def get_conn():
    conn = pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        db=DB_NAME,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )
    try:
        yield conn
    finally:
        conn.close()

# ==========================
#       MEMBUAT TABEL
# ==========================
def ensure_table():
    sql = """
    CREATE TABLE IF NOT EXISTS aws_stat (
      id INT AUTO_INCREMENT PRIMARY KEY,
      idaws VARCHAR(50),
      waktu DATETIME,
      windspeed DOUBLE,
      winddir DOUBLE,
      temp DOUBLE,
      rh DOUBLE,
      pressure DOUBLE,
      rain DOUBLE,
      solrad DOUBLE,
      netrad DOUBLE,
      watertemp DOUBLE,
      waterlevel DOUBLE,
      ta_min DOUBLE,
      ta_max DOUBLE,
      pancilevel DOUBLE,
      pancitemp DOUBLE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    """
    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(sql)

ensure_table()
print("✅ Tabel sudah siap")

# ==========================
#   FUNGSI INSERT DATA
# ==========================
def insert_record(data):
    sql = """
    INSERT INTO aws_stat
    (idaws, waktu, windspeed, winddir, temp, rh, pressure, rain,
     solrad, netrad, watertemp, waterlevel, ta_min, ta_max, pancilevel, pancitemp)
    VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """
    vals = (
        data.get("idaws"),
        data.get("waktu"),
        data.get("windspeed"),
        data.get("winddir"),
        data.get("temp"),
        data.get("rh"),
        data.get("pressure"),
        data.get("rain"),
        data.get("solrad"),
        data.get("netrad"),
        data.get("watertemp"),
        data.get("waterlevel"),
        data.get("ta_min"),
        data.get("ta_max"),
        data.get("pancilevel"),
        data.get("pancitemp")
    )
    with get_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, vals)

# ==========================
#   FUNGSI AMBIL DATA JSON
# ==========================
def fetch_json(url, timeout=15):
    resp = requests.get(url, timeout=timeout)
    resp.raise_for_status()
    return resp.json()

# ==========================
#   LOOP SCRAPING BERKALA
# ==========================
print("Mulai scraping")

while True:
    try:
        data = fetch_json(TARGET_URL)

        # Konversi waktu string ke datetime
        waktu = datetime.strptime(data["waktu"], "%Y-%m-%d %H:%M:%S")

        record = {
            "idaws": data.get("idaws"),
            "waktu": waktu,
            "windspeed": float(data.get("windspeed", 0)),
            "winddir": float(data.get("winddir", 0)),
            "temp": float(data.get("temp", 0)),
            "rh": float(data.get("rh", 0)),
            "pressure": float(data.get("pressure", 0)),
            "rain": float(data.get("rain", 0)),
            "solrad": float(data.get("solrad", 0)),
            "netrad": float(data.get("netrad", 0)),
            "watertemp": float(data.get("watertemp", 0)),
            "waterlevel": float(data.get("waterlevel", 0)),
            "ta_min": float(data.get("ta_min", 0)),
            "ta_max": float(data.get("ta_max", 0)),
            "pancilevel": float(data.get("pancilevel", 0)),
            "pancitemp": float(data.get("pancitemp", 0)),
        }

        insert_record(record)
        print(f"[{datetime.now().isoformat()}] ✅ Data tersimpan ke MySQL Laragon: waktu={waktu}, temp={record['temp']}°C")

    except Exception as e:
        print("❌ Error:", str(e))
        traceback.print_exc()

    time.sleep(SCRAPE_INTERVAL)
