CREATE TABLE /*_*/finance_period (
	-- @todo FIXME/CHECKME: not sure if this should or shouldn't be the PK
	id int NOT NULL PRIMARY KEY default 0,
	end_date binary(14) NOT NULL default '', -- standard MW timestamp field format, may not be appropriate for this extension as-is w/o some related code changes re:timestamp handling
)/*$wgDBTableOptions*/;