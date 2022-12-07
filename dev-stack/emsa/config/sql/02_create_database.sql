-- Database: dcp

-- DROP DATABASE dcp;

-- if Windows install, may need to skip LC_COLLATE and LC_CTYPE below
CREATE DATABASE dcp
    WITH OWNER = dcpadmin
    ENCODING = 'UTF8'
    TABLESPACE = pg_default
    LC_COLLATE = 'en_US.utf8'
    LC_CTYPE = 'en_US.utf8'
    CONNECTION LIMIT = -1;

