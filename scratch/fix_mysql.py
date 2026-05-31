import os

data_dir = r"C:\xampp\mysql\data"
log0 = os.path.join(data_dir, "ib_logfile0")
log1 = os.path.join(data_dir, "ib_logfile1")

try:
    if os.path.exists(log0):
        bak0 = log0 + ".bak"
        if os.path.exists(bak0):
            os.remove(bak0)
        os.rename(log0, bak0)
        print(f"Renamed {log0} to {bak0}")
    else:
        print(f"{log0} does not exist.")

    if os.path.exists(log1):
        bak1 = log1 + ".bak"
        if os.path.exists(bak1):
            os.remove(bak1)
        os.rename(log1, bak1)
        print(f"Renamed {log1} to {bak1}")
    else:
        print(f"{log1} does not exist.")

    print("Corrupted log files renamed successfully. Please try to restart MySQL from XAMPP Control Panel.")
except Exception as e:
    print(f"Error: {e}")
