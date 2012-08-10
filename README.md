# Dabloomd - dablooms * memcached like server -

# Dependencies

* php 5.3 higher
* [php-uv](https://github.com/chobie/php-uv)
* [php-memcache-parser](https://github.com/chobie/php-memcacheparser)

# Available Commands

* get
* set
* delete

# Experimental

# Acknowledgements

* bloom filter has `false positive`. so yo should check `get` result as it may contains `false positive` result.
* set / delete is very sensitive. you don't set / delete same key multiple time otherwise filter broken.

# License

MIT.