/* append to this file when changes are required to the live db */
/* it will be cleared when the changes go live */

ALTER TABLE testResults ADD COLUMN usedWorkaround ENUM("Yes", "No") DEFAULT NULL AFTER runs, ADD COLUMN workarounds TEXT DEFAULT NULL AFTER usedWorkaround; 
