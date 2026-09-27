<?php
/**
 * Minimal wpdb — gerçek MariaDB (mysqli) bağlantısı, Phase 6.2 attribution testleri.
 *
 * @package QR_Menu_Suite
 */

/**
 * Testler için mysqli tabanlı wpdb.
 */
class QRMS_MariaDB_Wpdb {
	/** @var mysqli */
	public $dbh;

	/** @var string */
	public $prefix = 'wp_';

	/** @var string */
	public $last_error = '';

	/** @var string[] */
	public $queries = array();

	/**
	 * @param mysqli $dbh Bağlantı.
	 */
	public function __construct( mysqli $dbh ) {
		$this->dbh = $dbh;
	}

	/**
	 * @return string
	 */
	public function get_charset_collate() {
		return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
	}

	/**
	 * @param bool $suppress Bastırma.
	 * @return bool
	 */
	public function suppress_errors( $suppress = true ) {
		unset( $suppress );
		return false;
	}

	/**
	 * @param string $sql    SQL.
	 * @param mixed  ...$args Argümanlar.
	 * @return string
	 */
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$escaped = array();
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || is_float( $arg ) ) {
				$escaped[] = (string) (int) $arg;
			} else {
				$escaped[] = "'" . $this->dbh->real_escape_string( (string) $arg ) . "'";
			}
		}

		$parts = preg_split( '/(%[dsf])/', $sql, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$out   = '';
		foreach ( $parts as $part ) {
			if ( preg_match( '/^%[dsf]$/', $part ) ) {
				$out .= array_shift( $escaped );
			} else {
				$out .= $part;
			}
		}

		return $out;
	}

	/**
	 * @param string $sql SQL.
	 * @return string|null
	 */
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		$res             = $this->dbh->query( $sql );
		if ( false === $res ) {
			$this->last_error = $this->dbh->error;
			return null;
		}
		$row = $res->fetch_row();
		$res->free();
		return $row ? $row[0] : null;
	}

	/**
	 * @param string $sql  SQL.
	 * @param string $mode Mod (ARRAY_A vb.).
	 * @return array<int, object|array<string, mixed>>
	 */
	public function get_results( $sql, $mode = null ) {
		$this->queries[] = $sql;
		$res             = $this->dbh->query( $sql );
		if ( false === $res ) {
			$this->last_error = $this->dbh->error;
			return array();
		}
		$rows = array();
		if ( ARRAY_A === $mode ) {
			while ( $row = $res->fetch_assoc() ) {
				$rows[] = $row;
			}
		} else {
			while ( $row = $res->fetch_object() ) {
				$rows[] = $row;
			}
		}
		$res->free();
		return $rows;
	}

	/**
	 * @param string $sql  SQL.
	 * @param string $mode Mod.
	 * @return object|array<string, mixed>|null
	 */
	public function get_row( $sql, $mode = null ) {
		unset( $mode );
		$this->queries[] = $sql;
		$res             = $this->dbh->query( $sql );
		if ( false === $res ) {
			$this->last_error = $this->dbh->error;
			return null;
		}
		$row = $res->fetch_object();
		$res->free();
		return $row ?: null;
	}

	/**
	 * @param string               $table  Tablo.
	 * @param array<string, mixed> $data   Veri.
	 * @param array<int, string>|null $format Format.
	 * @return int|false
	 */
	public function insert( $table, $data, $format = null ) {
		unset( $format );
		$cols = array();
		$vals = array();
		foreach ( $data as $col => $val ) {
			$cols[] = '`' . str_replace( '`', '``', $col ) . '`';
			if ( null === $val ) {
				$vals[] = 'NULL';
			} elseif ( is_int( $val ) ) {
				$vals[] = (string) $val;
			} else {
				$vals[] = "'" . $this->dbh->real_escape_string( (string) $val ) . "'";
			}
		}
		$sql = 'INSERT INTO ' . $table . ' (' . implode( ', ', $cols ) . ') VALUES (' . implode( ', ', $vals ) . ')';
		$this->queries[] = $sql;
		if ( ! $this->dbh->query( $sql ) ) {
			$this->last_error = $this->dbh->error;
			return false;
		}
		return (int) $this->dbh->insert_id;
	}
}
