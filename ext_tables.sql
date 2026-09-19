CREATE TABLE tx_webconjev_decision
(
	identifier           varchar(64)     DEFAULT '' NOT NULL,
	title                varchar(255)    DEFAULT '' NOT NULL,
	description          text,
	state_template       text,
	model                varchar(64)     DEFAULT '' NOT NULL,
	confidence_threshold double          DEFAULT '0.6' NOT NULL,
	cache_lifetime       int(11)         DEFAULT '-1' NOT NULL,
	default_outcome      varchar(255)    DEFAULT '' NOT NULL,
	questions            int(11) unsigned DEFAULT '0' NOT NULL,

	KEY identifier (identifier)
);

CREATE TABLE tx_webconjev_question
(
	decision     int(11) unsigned DEFAULT '0' NOT NULL,
	name         varchar(64)  DEFAULT '' NOT NULL,
	type         varchar(16)  DEFAULT 'choice' NOT NULL,
	instructions text,
	criteria     int(11) unsigned DEFAULT '0' NOT NULL,

	KEY decision (decision)
);

CREATE TABLE tx_webconjev_criterion
(
	question      int(11) unsigned DEFAULT '0' NOT NULL,
	identifier    varchar(64)  DEFAULT '' NOT NULL,
	description   text,
	outcome_value varchar(255) DEFAULT '' NOT NULL,

	KEY question (question)
);

CREATE TABLE tx_webconjev_run
(
	-- No TCA: this is a log, not a record type, so the standard columns are declared here
	-- rather than added for us.
	uid                 int(11) unsigned NOT NULL AUTO_INCREMENT,
	pid                 int(11) unsigned DEFAULT '0' NOT NULL,
	crdate              int(11) unsigned DEFAULT '0' NOT NULL,
	tstamp              int(11) unsigned DEFAULT '0' NOT NULL,

	decision            int(11) unsigned DEFAULT '0' NOT NULL,
	decision_identifier varchar(64)  DEFAULT '' NOT NULL,
	context             varchar(32)  DEFAULT '' NOT NULL,
	origin              varchar(255) DEFAULT '' NOT NULL,
	model               varchar(64)  DEFAULT '' NOT NULL,
	state_hash          varchar(64)  DEFAULT '' NOT NULL,
	question_count      int(11)      DEFAULT '0' NOT NULL,
	duration_ms         double       DEFAULT '0' NOT NULL,
	input_tokens        int(11)      DEFAULT '0' NOT NULL,
	output_tokens       int(11)      DEFAULT '0' NOT NULL,
	cost_usd            double       DEFAULT '0' NOT NULL,
	from_cache          smallint(5) unsigned DEFAULT '0' NOT NULL,
	is_fallback         smallint(5) unsigned DEFAULT '0' NOT NULL,
	fallback_reason     varchar(255) DEFAULT '' NOT NULL,
	answers             text,

	PRIMARY KEY (uid),
	KEY decision_time (decision, crdate),
	KEY crdate (crdate)
);

CREATE TABLE tx_powermailcond_domain_model_rule
(
	tx_webconjev_decision  int(11) unsigned DEFAULT '0' NOT NULL,
	tx_webconjev_question  varchar(64)  DEFAULT '' NOT NULL,
	tx_webconjev_expect    varchar(255) DEFAULT '' NOT NULL,
	tx_webconjev_threshold double       DEFAULT '0.6' NOT NULL
);

CREATE TABLE tx_powermail_domain_model_form
(
	tx_webconjev_routing_decision int(11) unsigned DEFAULT '0' NOT NULL,
	tx_webconjev_routing_question varchar(64) DEFAULT '' NOT NULL
);

CREATE TABLE tx_powermail_domain_model_mail
(
	tx_webconjev_routing_summary varchar(255) DEFAULT '' NOT NULL
);
