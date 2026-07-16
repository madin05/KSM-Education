-- Matiin sensor pengecekan relasi lagi
SET FOREIGN_KEY_CHECKS = 0;

-- Paksa sistem ngebaca file data asli XAMPP
ALTER TABLE journal_system2.drafts IMPORT TABLESPACE;
ALTER TABLE journal_system2.journals IMPORT TABLESPACE;
ALTER TABLE journal_system2.opinions IMPORT TABLESPACE;
ALTER TABLE journal_system2.sync_queue IMPORT TABLESPACE;
ALTER TABLE journal_system2.uploads IMPORT TABLESPACE;
ALTER TABLE journal_system2.users IMPORT TABLESPACE;
ALTER TABLE journal_system2.user_preferences IMPORT TABLESPACE;

-- Nyalain lagi sensornya biar database lu aman
SET FOREIGN_KEY_CHECKS = 1;