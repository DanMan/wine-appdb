use apidb;

drop table if exists testResults;

/*
 * Version Test results
 */
create table testResults (
        testingId       int not null auto_increment,
	versionId       int not null,
        whatWorks	text,
        whatDoesnt	text,
        whatNotTested   text,
        testedDate      datetime not null,
        distributionId  int not null,
	testedRelease 	tinytext,
	staging tinyint(1) not null default '0',
	installs	enum('Yes','No','No, but has workaround','N/A') NOT NULL default 'Yes',
	runs		enum('Yes','No','Not Installable') NOT NULL default 'Yes',
	usedWorkaround    enum('Yes','No') default NULL,
	workarounds text default NULL,
	testedRating  	enum('Platinum','Gold','Silver','Bronze','Garbage') NOT NULL,
        comments        text,
	submitTime	datetime NOT NULL,
	submitterId	int(11) NOT NULL default '0',
	state		enum('accepted','queued','rejected','pending','deleted') NOT NULL default 'accepted',
    gpuMfr  enum('AMD', 'Intel', 'Nvidia', 'Other', 'Unknown') default NULL,
    graphicsDriver enum('open source', 'proprietary', 'unknown') default NULL, 
        key(testingId)
);
