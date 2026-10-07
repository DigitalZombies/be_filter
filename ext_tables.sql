#
# Saved filters. A filter belongs to the backend user who saved it and carries the
# values as well as the filters displayed in the bar. Sharing is either private,
# limited to backend groups, or public.
#
CREATE TABLE tx_befilter_preset (
	uid int(11) NOT NULL auto_increment,
	be_user int(11) UNSIGNED DEFAULT 0 NOT NULL,
	table_name varchar(255) DEFAULT '' NOT NULL,
	title varchar(255) DEFAULT '' NOT NULL,
	filter_values mediumtext,
	visible_filters mediumtext,
	visibility varchar(16) DEFAULT 'private' NOT NULL,
	be_groups text,

	PRIMARY KEY (uid),
	KEY befilter_table (table_name, be_user)
);
