<?php
namespace Me\Stenberg\Content\Staging\DB;

use Me\Stenberg\Content\Staging\Models\Model;
use Me\Stenberg\Content\Staging\Object_Watcher;

abstract class DAO {

	protected $wpdb;

	protected function __constuct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function find( $id ) {
		$old = $this->get_from_map( $id );
		if ( ! is_null( $old ) ) {
			return $old;
		}

		$query  = $this->wpdb->prepare( $this->select_stmt(), $id );
		$result = $this->wpdb->get_row( $query, ARRAY_A );

		if ( $result === null ) {
			return null;
		}

		return $this->create_object( $result );
	}

	/**
	 * Get any object that matches one of the provided IDs.
	 *
	 * @param array $ids Array of IDs.
	 * @return array
	 */
	public function find_by_ids( $ids ) {
		$collection = array();

		foreach ( $ids as $index => $id ) {
			$old = $this->get_from_map( $id );
			if ( ! is_null( $old ) ) {
				$collection[] = $old;
				unset( $ids[$index] );
			}
		}

		if ( count( $ids ) < 1 ) {
			return $collection;
		}

		$query = $this->wpdb->prepare( $this->select_by_ids_stmt( $ids ), $ids );
		foreach ( $this->wpdb->get_results( $query, ARRAY_A ) as $record ) {
			$collection[] = $this->create_object( $record );
		}

		return $collection;
	}

	public function insert( Model $obj ) {
		$this->do_insert( $obj );
		$this->add_to_map( $obj );
	}

	/**
	 * @param array $raw
	 * @return Model
	 */
	public function create_object( array $raw ) {
		$key = $this->unique_key( $raw );
		$old = $this->get_from_map( $key );
		if ( ! is_null( $old ) ) {
			return $old;
		}
		$obj = $this->do_create_object( $raw );
		$this->add_to_map( $obj );
		return $obj;
	}

	/**
	 * @param Model $obj
	 * @return array
	 */
	public function create_array( Model $obj ) {
		return $this->do_create_array( $obj );
	}

	/**
	 * Update data.
	 *
	 * @param array $data
	 * @param array $where
	 * @param array $format
	 * @param array $where_format
	 */
	public function update( $data, $where, $format = null, $where_format = null ) {
		$this->wpdb->update( $this->get_table(), $data, $where, $format, $where_format );
	}

	public function delete( $where, $format ) {
		$this->wpdb->delete( $this->get_table(), $where, $format );
	}

	/**
	 * Wrapper for WordPress function get_post_meta.
	 *
	 * @see http://codex.wordpress.org/Function_Reference/get_post_meta
	 *
	 * @param int $post_id
	 * @param string $key
	 * @param bool $single
	 * @return mixed
	 */
	public function get_post_meta( $post_id, $key = '', $single = false ) {
		return get_post_meta( $post_id, $key, $single );
	}

	/**
	 * Wrapper for WordPress function update_post_meta.
	 *
	 * @see http://codex.wordpress.org/Function_Reference/update_post_meta
	 *
	 * @param int $post_id
	 * @param string $meta_key
	 * @param mixed $meta_value
	 * @param mixed $prev_value
	 * @return bool|int
	 */
	public function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = null ) {
		return update_post_meta( $post_id, $meta_key, $meta_value, $prev_value );
	}

	protected abstract function get_table();
	protected abstract function target_class();
	protected abstract function unique_key( array $raw );
	protected abstract function select_stmt();
	protected abstract function select_by_ids_stmt( array $ids );
	protected abstract function do_insert( Model $obj );
	protected abstract function do_create_object( array $raw );
	protected abstract function do_create_array( Model $obj );
	protected abstract function format();

	/**
	 * Take an array of values that will be used in a SQL IN clause and
	 * create placeholders that can be used together with wpdb->prepare.
	 *
	 * Example of a SQL IN clause:
	 * SELECT * FROM table WHERE id IN (1, 2, 3, ...)
	 *
	 * @param array $values Values to use in SQL IN clause.
	 * @param string $format Format of values in $values, %s for strings, %d for digits.
	 * @return string A string of placeholders, e.g. %d, %d, %d, ...
	 */
	protected function in_clause_placeholders( $values, $format = '%s' ) {

		$nbr_of_values = count( $values );

		if ( $nbr_of_values <= 0 ) {
			return '';
		}

		// Prepare the right amount of placeholders.
		$placeholders = array_fill( 0, $nbr_of_values, $format );

		// Transform array of placeholders into comma separated string.
		return implode( ', ', $placeholders );
	}

	/**
	 * The content staging database is most likely created from a dump of the
	 * production database. In this dumb the production domain name might
	 * have been replaced by a domain name specific for the content stage.
	 * To be able to compare GUIDs between content stage and production we
	 * need to normalize the URL, in this case that means stripping the
	 * domain name.
	 *
	 * @param string $guid
	 * @return string
	 */
	protected function normalize_guid( $guid ) {

		$info = parse_url( $guid );

		if ( ! isset( $info['scheme'] ) || ! isset( $info['host'] ) ) {
			return null;
		}

		return str_replace( $info['scheme'] . '://' . $info['host'], '', $guid );
	}

	/**
	 * Get object that has been instantiated and added to the object watcher.
	 * Return null if object is not found in watcher.
	 *
	 * @param $id
	 * @return Model
	 */
	private function get_from_map( $id ) {
		return Object_Watcher::exists( $this->target_class(), $id );
	}

	private function add_to_map( Model $obj ) {
		Object_Watcher::add( $obj );
	}

}