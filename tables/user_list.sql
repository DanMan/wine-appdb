use apidb;

drop table if exists user_list;

create table user_list (
	stamp           timestamp not null,
        userid          int not null auto_increment,
	password	text not null,
	realname	text not null,
	email		text not null,
        created         datetime not null,
        status          int(4),
	perm		int(4),
        CVSrelease      text,
        unique key(userid),
	unique(email)
);

insert into user_list values (NOW(), 0, password('testing'), 'Administrator',
	'Admin@localhost', NOW(), 0, 0xffffffff);
update user_list set userid = 1000 where email = 'Admin@localhost';

