# Setting up a new EMSA Database #

Before continuing, ensure that you have a database server configured and running PostgreSQL with the following minimum requirements:

## Minimum System Requirements ##
- PostgreSQL 10 or higher,
- `plpgsql`, `pg_trgm`, and `fuzzystrmatch` extensions installed or available to be installed,
- `max_prepared_transactions` set to **`1`** or higher (min. value of **`5`** recommended).
- Recommend setting `max_connections` to at least **`100`** if possible, depending on your environment. 

## Installation Steps ##
The following steps will set up the database for EMSA for a from-scratch (first time) installation.  If you are upgrading an existing EMSA installation from a previous release, skip these steps and follow the steps in scripts found within the `patches` directory to bring your installation up-to-date for the latest release.

1. Run `create_roles.sql` to create the necessary group and login roles.

2. Create an empty database named `dcp` (either manually with the following properties, or run `create_database.sql`).
    ```
    OWNER = dcpadmin
    ENCODING = 'UTF8'
    TABLESPACE = pg_default
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    CONNECTION LIMIT = -1
    ```

    (For Windows installations, it may be necessary to skip the `LC_COLLATE` and `LC_CTYPE` directives due to locale differences.)
    
    **For all of the following steps, scripts should be run against this `dcp` database.**

3. If needed, run `create_extensions.sql` to install the `plpgsql`, `fuzzystrmatch`, and `pg_trgm` extensions.

3. Set a unique password for the login roles `droolsuser` and `dcpadmin`.  These are the main users that EMSA will use to read from and write to the database, and should be strong, protected passwords!
  
4. Run `create_schema.sql` to create the `elr` schema in the `dcp` database.

5. Run `create_tables.sql` to create the required database tables.

6. Run `create_functions.sql` to create required functions and triggers.

7. Run `populate_base_data.sql` to set up basic common configuration items and system menus.