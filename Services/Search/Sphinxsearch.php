<?php

namespace Search\SphinxsearchBundle\Services\Search;

class Sphinxsearch
{
	/**
	 * @var string $host
	 */
	private $host;

	/**
	 * @var string $port
	 */
	private $port;

	/**
	 * @var string $socket
	 */
	private $socket;

	/**
	 * @var array $indexes
	 *
	 * $this->indexes should look like:
	 *
	 * $this->indexes = array(
	 *   'IndexLabel' => 'Index name as defined in sphinxsearch.conf',
	 *   ...,
	 * );
	 */
	private $indexes;

	/**
	 * @var SphinxClient $sphinx
	 */
	private $sphinx;

	/**
	 * Constructor.
	 *
	 * @param string $host The server's host name/IP.
	 * @param string $port The port that the server is listening on.
	 * @param string $socket The UNIX socket that the server is listening on.
	 * @param array $indexes The list of indexes that can be used.
	 */
	public function __construct($host = 'localhost', $port = '9312', $socket = null, array $indexes = array())
	{
		$this->host = $host;
		$this->port = $port;
		$this->socket = $socket;
		$this->indexes = $indexes;

		$this->sphinx = new \SphinxClient();
		if( $this->socket !== null )
			$this->sphinx->setServer($this->socket);
		else
			$this->sphinx->setServer($this->host, $this->port);
	}

	/**
	 * Escape the supplied string.
	 *
	 * @param string $string The string to be escaped.
	 *
	 * @return string The escaped string.
	 */
	public function escapeString($string)
	{
		return $this->sphinx->escapeString($string);
	}

	/**
	 * Set the desired match mode.
	 *
	 * @param int $mode The matching mode to be used.
	 */
	public function setMatchMode($mode)
	{
		$this->sphinx->setMatchMode($mode);
	}

	/**
	 * Set the desired search filter.
	 *
	 * @param string $attribute The attribute to filter.
	 * @param array $values The values to filter.
	 * @param bool $exclude Is this an exclusion filter?
	 */
	public function setFilter($attribute, $values, $exclude = false)
	{
		$this->sphinx->setFilter($attribute, $values, $exclude);
	}

	/**
	 * Search for the specified query string.
	 *
	 * @param string $query The query string that we are searching for.
	 * @param array $indexes The indexes to perform the search on.
	 *
	 * @return array The results of the search.
	 *
	 * $indexes should look like:
	 *
	 * $indexes = array(
	 *   'IndexLabel' => array(
	 *     'result_offset' => (int), // optional unless result_limit is set
	 *     'result_limit'  => (int), // optional unless result_offset is set
	 *     'field_weights' => array( // optional
	 *       'FieldName'   => (int),
	 *       ...,
	 *     ),
	 *   ),
	 *   ...,
	 * );
	 */
	public function search($query, array $indexes, $escapeQuery = true)
	{
		if( $escapeQuery )
			$query = $this->sphinx->escapeString($query);

		$indexnames = '';
		$results = array();
		$i = 0;
		foreach( $indexes as $label => $options ) {
		
			/**
			 * Ensure that the label corresponds to a defined index.
			 */
			if( !isset($this->indexes[$label]) )
				continue;

			/**
			 * Set the offset and limit for the returned results.
			 */
			if( isset($options['result_offset']) && isset($options['result_limit']) )
				$this->sphinx->setLimits($options['result_offset'], $options['result_limit'],20000);

			/**
			 * Weight the individual fields.
			 */
			if( isset($options['field_weights']) )
				$this->sphinx->setFieldWeights($options['field_weights']);

			/*
			* Create string of index names for SphinxAPI query function.
			*/
			if($i == 1){
				$indexnames .= ' ';
			}
			$indexnames .= $this->indexes[$label];
			$i++;
		}

		/**
		 * Perform the query.
		 */
		$results = $this->sphinx->query($query, $indexnames);

		if( $results['status'] !== SEARCHD_OK )
			throw new \RuntimeException(sprintf('Searching index "%s" for "%s" failed with error "%s".', $label, $query, $this->sphinx->getLastError()));

		/**
		 * If only one index was searched, return that index's results directly.
		 */
		if( count($indexes) === 1 && count($results) === 1 )
			$results = reset($results);

		/**
		 * FIXME: Throw an exception if $results is empty?
		 */
		return $results;
	}

	/**
	 * Reset all previously set filters.
	 *
	 */
        public function resetFilters() {
		$this->sphinx->resetFilters();
	}

	/**
	 * Adds query with the current settings to multi-query batch. This method doesn't affect current settings (sorting, filtering, grouping etc.) in any way.
	 * @param string $query The query string that we are searching for.
	 * @param array $indexes The indexes to perform the search on.
	 *
         * $indexes = array(
	 *   'IndexLabel',
         *   'IndexLabel2'
         *  );
	 */
        public function addQuery($search_str, $indexes) {
		$indexnames = '';

		//Create string on index names
		foreach( $indexes as $label) {

			/**
			 * Ensure that the label corresponds to a defined index.
			 */
			if(isset($this->indexes[$label])){

				/*
				* Create string of index names for SphinxAPI query function.
				*/
				$indexnames .= $this->indexes[$label].' ';
			} 
		}

		$this->sphinx->addQuery($search_str, $indexnames);
	}

   /**
	* Connects to searchd, runs a batch of all queries added using SphinxClient::addQuery, obtains and returns the result sets.
	*
    */
    public function runQueries() {
		return $this->sphinx->runQueries();
	}

	/**
	* Set sort mode for search.
	* @param $mode Sortmode as specifed in Sphinx API Doc on of: 
	*		SPH_SORT_RELEVANCE	Sort by relevance in descending order (best matches first).
	*		SPH_SORT_ATTR_DESC	Sort by an attribute in descending order (bigger attribute values first).
	*		SPH_SORT_ATTR_ASC	Sort by an attribute in ascending order (smaller attribute values first).
	*		SPH_SORT_TIME_SEGMENTS	Sort by time segments (last hour/day/week/month) in descending order, and then by relevance in descending order.
	*		SPH_SORT_EXTENDED	Sort by SQL-like combination of columns in ASC/DESC order.
	*		SPH_SORT_EXPR
	* @param $sortby String name of field to sort.
	*/
	public function setSortMode($mode, $sortby){
		$this->sphinx->setSortMode($mode, $sortby);
	}
}

