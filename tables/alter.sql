/* append to this file when changes are required to the live db */
/* it will be cleared when the changes go live */
ALTER TABLE `testResults` CHANGE `submitTime` `submitTime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

/* to fix the incorrect entries*/
UPDATE testResults SET submitTime = testedDate WHERE submitTime = '0000-00-00 00:00:00'
