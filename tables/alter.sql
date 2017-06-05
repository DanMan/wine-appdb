/* append to this file when changes are required to the live db */
/* it will be cleared when the changes go live */
ALTER TABLE testResults ADD column staging tinyint(1) not null default '0' AFTER testedRelease;
