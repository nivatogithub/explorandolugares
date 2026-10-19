from pathlib import Path
import subprocess,unittest
class BackupRetentionTests(unittest.TestCase):
 def test_retention_safety_and_generations(self):
  subprocess.run(['php',str(Path(__file__).with_name('backup-retention.php'))],check=True)
if __name__=='__main__':unittest.main()
