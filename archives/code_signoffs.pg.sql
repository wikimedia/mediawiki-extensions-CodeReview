CREATE TABLE code_signoffs (
  cs_repo_id INTEGER NOT NULL,
  cs_rev_id INTEGER NOT NULL,
  cs_user INTEGER NOT NULL,
  cs_user_text TEXT NOT NULL,
  cs_flag TEXT NOT NULL,
  cs_timestamp TIMESTAMPTZ NOT NULL default NOW(),
  cs_timestamp_struck TEXT NOT NULL default 'infinity'
);

CREATE UNIQUE INDEX cs_repo_rev_user_flag_tstruck ON code_signoffs (cs_repo_id, cs_rev_id, cs_user_text, cs_flag, cs_timestamp_struck);
CREATE INDEX cs_repo_repo_rev_timestamp ON code_signoffs (cs_repo_id, cs_rev_id, cs_timestamp);
