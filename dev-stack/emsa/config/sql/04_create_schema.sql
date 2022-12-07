\c dcp;
-- Schema: elr

-- DROP SCHEMA elr;
CREATE SCHEMA elr
    AUTHORIZATION dcpadmin;

GRANT ALL ON SCHEMA elr TO "elr-su";
GRANT ALL ON SCHEMA elr TO "elr-rw";
GRANT ALL ON SCHEMA elr TO "elr-ro";
GRANT ALL ON SCHEMA elr TO droolsuser;

