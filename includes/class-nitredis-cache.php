<?php
/**
 * NitRedis — Cache Helper
 *
 * Thin wrapper around the WordPress object cache API for flushing,
 * stats gathering, and warm-up utilities.
 *
 * @package NitRedis
 */

defined( 'ABSPATH' ) || exit;

class NitRedis_Cache {

    /**
     * Flush the entire Redis cache.
     *
     * @return bool
     */
    public static function flush() {
        $client = NitRedis_Connection::connect();
        if ( ! $client ) {
            return false;
        }

        try {
            if ( $client instanceof Redis ) {
                $client->flushDB();
            } else {
                $client->flushdb();
            }
            return true;
        } catch ( Exception $e ) {
            error_log( 'NitRedis flush error: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Retrieve Redis INFO stats.
     *
     * @return array|false
     */
    public static function get_stats() {
        $client = NitRedis_Connection::connect();
        if ( ! $client ) {
            return false;
        }

        try {
            if ( $client instanceof Redis ) {
                $info = $client->info();
            } else {
                $info = $client->info();
                // Predis returns an array-like object.
                $info = (array) $info;
            }
            return $info;
        } catch ( Exception $e ) {
            return false;
        }
    }

    /**
     * Return a curated subset of Redis metrics for the dashboard.
     *
     * @return array
     */
    public static function get_metrics() {
        $info = self::get_stats();
        if ( ! $info ) {
            return [];
        }

        // Handle nested sections (PhpRedis returns a flat array when called without section).
        $flat = [];
        foreach ( $info as $key => $value ) {
            if ( is_array( $value ) ) {
                foreach ( $value as $k => $v ) {
                    $flat[ $k ] = $v;
                }
            } else {
                $flat[ $key ] = $value;
            }
        }

        $hits      = (int) ( $flat['keyspace_hits']   ?? 0 );
        $misses    = (int) ( $flat['keyspace_misses'] ?? 0 );
        $total     = $hits + $misses;
        $hit_ratio = $total > 0 ? round( ( $hits / $total ) * 100, 2 ) : 0;

        return [
            'redis_version'       => $flat['redis_version']       ?? 'N/A',
            'uptime_in_seconds'   => $flat['uptime_in_seconds']   ?? 0,
            'connected_clients'   => $flat['connected_clients']   ?? 0,
            'used_memory_human'   => $flat['used_memory_human']   ?? 'N/A',
            'used_memory_peak_human' => $flat['used_memory_peak_human'] ?? 'N/A',
            'mem_fragmentation_ratio' => $flat['mem_fragmentation_ratio'] ?? 'N/A',
            'keyspace_hits'       => $hits,
            'keyspace_misses'     => $misses,
            'hit_ratio'           => $hit_ratio,
            'total_commands_processed' => $flat['total_commands_processed'] ?? 0,
            'total_connections_received' => $flat['total_connections_received'] ?? 0,
            'evicted_keys'        => $flat['evicted_keys']        ?? 0,
            'expired_keys'        => $flat['expired_keys']        ?? 0,
            'rdb_last_bgsave_status' => $flat['rdb_last_bgsave_status'] ?? 'N/A',
            'aof_enabled'         => $flat['aof_enabled']         ?? 0,
            'role'                => $flat['role']                ?? 'N/A',
        ];
    }

    /**
     * Count the number of keys currently stored (with optional prefix filter).
     *
     * @return int
     */
    public static function key_count() {
        $client = NitRedis_Connection::connect();
        if ( ! $client ) {
            return 0;
        }

        try {
            if ( $client instanceof Redis ) {
                return $client->dbSize();
            } else {
                return $client->dbsize();
            }
        } catch ( Exception $e ) {
            return 0;
        }
    }

    /**
     * Ping the Redis server.
     *
     * @return bool
     */
    public static function ping() {
        $client = NitRedis_Connection::connect();
        if ( ! $client ) {
            return false;
        }

        try {
            if ( $client instanceof Redis ) {
                return $client->ping() === true || $client->ping() === '+PONG';
            } else {
                $response = $client->ping();
                return $response->getPayload() === 'PONG';
            }
        } catch ( Exception $e ) {
            return false;
        }
    }
}
