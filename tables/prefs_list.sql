use apidb;

drop table if exists prefs_list;

CREATE TABLE prefs_list (
	id		int auto_increment not null,
	name		varchar(32),
	def_value	text,
	value_list	text,
	description	text,
	show_for_group	text,

	primary key(id)
);

INSERT INTO prefs_list (name,def_value,value_list,description) VALUES ('debug', 'no', 'yes|no', 'Enable debugging information');
INSERT INTO prefs_list (name,def_value,value_list,description) VALUES ('window:offsite', 'no', 'yes|no', 'Display offsite URLs in a new window');
INSERT INTO prefs_list (name,def_value,value_list,description) VALUES ('confirm_comment_deletion', 'yes', 'yes|no', 'Ask why you are deleting a comment before deleting it');
INSERT INTO prefs_list (name,def_value,value_list,description) VALUES ('send_email', 'yes', 'yes|no', 'Send email notifications');
INSERT INTO prefs_list (name,def_value,value_list,description) VALUES ('htmleditor', 'for supported browsers', 'always|for supported browsers|never', 'Display a graphical HTML editor in certain text fields');
INSERT INTO prefs_list (name,def_value,value_list,description,show_for_group) VALUES ('disable_global_emails', 'no', 'yes|no', 'Disable global e-mail notifications (only send for maintained apps)', 'admin');
