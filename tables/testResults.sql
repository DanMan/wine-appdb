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
	installs	enum('Yes','No','N/A') NOT NULL default 'Yes',
	runs		enum('Yes','No','Not Installable') NOT NULL default 'Yes',
	testedRating  	enum('Platinum','Gold','Silver','Bronze','Garbage') NOT NULL,
        comments        text,
	submitTime	datetime NOT NULL,
	submitterId	int(11) NOT NULL default '0',
	state		enum('accepted','queued','rejected','pending','deleted') NOT NULL default 'accepted',
        key(testingId)
);
