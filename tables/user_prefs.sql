CREATE TABLE user_prefs (
        userid          int not null,
        name            varchar(64) not null,
	value		text,
        key(userid),
	key(name)
);
