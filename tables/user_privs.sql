use apidb;

drop table if exists user_privs;

CREATE TABLE user_privs (
	userid		int not null,
	priv		varchar(64) not null,
	primary key(userid)
);

insert into user_privs values (1000, 'admin');
