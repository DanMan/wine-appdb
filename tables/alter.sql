/* append to this file when changes are required to the live db */
/* it will be cleared when the changes go live */
ALTER TABLE testResults ADD COLUMN gpuMfr ENUM('AMD', 'Intel', 'Nvidia', 'Other', 'Unknown') DEFAULT NULL, ADD COLUMN graphicsDriver ENUM('open source', 'proprietary', 'unknown') DEFAULT NULL;
