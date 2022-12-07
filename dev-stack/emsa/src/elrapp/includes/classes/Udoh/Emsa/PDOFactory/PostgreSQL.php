<?php

namespace Udoh\Emsa\PDOFactory;

/**
 * Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * In addition, this program is also subject to certain additional terms. You should
 * have received a copy of these additional terms immediately following the terms and
 * conditions of the GNU Affero General Public License which accompanied the program.
 * If not, please request a copy in writing from the Utah Department of Health at
 * the address below.
 * 
 * If you have questions concerning this license or the applicable additional terms,
 * you may contact us in writing at:
 * Utah Department of Health, P.O. Box 141010, Salt Lake City, UT 84114-1010 USA.
 * 
 * @copyright Copyright (c) 2017 Utah Department of Technology Services and Utah Department of Health
 */

use Exception;
use PDO;
use PDOException;

/**
 * PDO container for connecting to the EMSA database
 *
 * @author Josh Ridderhoff <jridderhoff@utah.gov>
 */
class PostgreSQL
{

    /**
     * PDO connection to the EMSA database
     * @var PDO
     */
    private $connection;

    /** List of valid PostgreSQL schemas
     * @var string[] */
    private $validSchemas = array(
        'elr'
    );

    /**
     * Create a new PDO connection to the EMSA database.
     *
     * @param string $dbHost          Database hostname or IP address
     * @param string $dbPort          Database port
     * @param string $dbName          Database name
     * @param string $dbUser          Database username
     * @param string $dbPass          Database password
     * @param string $dbSchema        [Optional]<br>If specified, set the search_path to this schema name as well as public.  If empty, default search_path is used.
     * @param string $sslModeOverride [Optional]<br>Overrides the default SSL mode used to connect to the database (as configured in the external config file).  Supports 'disable', 'allow', 'prefer' (default value), or 'require'.  See {@link https://www.postgresql.org/docs/current/libpq-connect.html www.postgresql.org/docs/current/libpq-connect.html} for more information.
     *
     * @throws Exception
     */
    public function __construct(string $dbHost, string $dbPort, string $dbName, string $dbUser, string $dbPass, ?string $dbSchema = null, ?string $sslModeOverride = null)
    {
        $validSSLModes = [
            'disable' => 'disable',
            'allow' => 'allow',
            'prefer' => 'prefer',
            'require' => 'require'
        ];

        if (!empty($sslModeOverride) && array_key_exists(strtolower(trim($sslModeOverride)), $validSSLModes)) {
            $cleanSSLMode = $validSSLModes[strtolower(trim($sslModeOverride))];
        } elseif (array_key_exists(strtolower(trim(DB_SSLMODE)), $validSSLModes)) {
            $cleanSSLMode = $validSSLModes[strtolower(trim(DB_SSLMODE))];
        } else {
            $cleanSSLMode = $validSSLModes['prefer'];
        }

        try {
            $dsnStr = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName;user=$dbUser;password=$dbPass;sslmode=$cleanSSLMode";
            $this->connection = new PDO($dsnStr);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if (!empty($dbSchema) && in_array($dbSchema, $this->validSchemas)) {
                $this->connection->exec('SET search_path TO ' . $dbSchema . ',public;');
            }
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Ensure no references to the PDO instance.
     */
    public function __destruct()
    {
        $this->connection = null;
        $this->validSchemas = null;
    }

    /**
     * Get a PDO object with a connection to the EMSA database
     * 
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

}
