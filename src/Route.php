<?php
/**
 * 
 * This file is part of the Aura for PHP.
 * 
 * @package Aura.Router
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Router;

use ArrayObject;
use Closure;

/**
 * 
 * Represents an individual route with a name, path, params, values, etc.
 *
 * In general, you should never need to instantiate a Route directly. Use the
 * RouteFactory instead, or the Router.
 * 
 * @package Aura.Router
 * 
 */
class Route
{
    /**
     * 
     * The name for this Route.
     * 
     * @var string
     * 
     */
    protected $name;

    /**
     * 
     * The path for this Route with param tokens.
     * 
     * @var string
     * 
     */
    protected $path;

    /**
     * 
     * A map of param tokens to their regex subpatterns.
     * 
     * @var array
     * 
     */
    protected $params = array();

    /**
     * 
     * A map of param tokens to their default values; if this Route is
     * matched, these will retain the corresponding values from the param 
     * tokens in the matching path.
     * 
     * @var array
     * 
     */
    protected $values = array();

    /**
     * 
     * The `REQUEST_METHOD` value must match one of the methods in this array;
     * method; e.g., `'GET'` or `['POST', 'DELETE']`.
     * 
     * @var array
     * 
     */
    protected $method = array();

    /**
     * 
     * When true, the `HTTPS` value must be `on`, or the `SERVER_PORT` must be
     * 443.  When false, neither of those values may be present.  When null, 
     * it is ignored.
     * 
     * @var bool
     * 
     */
    protected $secure = null;

    /**
     * 
     * A callable to provide custom matching logic against the 
     * server values and matched params from this Route. The signature must be 
     * `function(array $server, \ArrayObject $matches)` and must return a 
     * boolean: true to accept this Route match, or false to deny the match. 
     * Note that this allows a wide range of manipulations, and further allows 
     * the developer to modify the matched params as needed.
     * 
     * @var callable
     * 
     * @see isMatch()
     * 
     */
    protected $is_match;

    /**
     * 
     * A callable to modify path-generation values. The signature 
     * must be `function($route, array $data)`; its return value is an array 
     * of data to be used in the path. The `$route` is this Route object, and 
     * `$data` is the set of key-value pairs to be interpolated into the path
     * as provided by the caller.
     * 
     * @var callable
     * 
     * @see generate()
     * 
     */
    protected $generate;

    /**
     * 
     * If routable, this route should be used in matching.  If not, it should
     * be used only to generate a path.
     * 
     * @var bool
     * 
     */
    protected $routable;

    /**
     * 
     * A prefix for the Route name, generally from attached route groups.
     * 
     * @var string
     * 
     */
    protected $name_prefix;

    /**
     * 
     * A prefix for the Route path, generally from attached route groups.
     * 
     * @var string
     * 
     */
    protected $path_prefix;

    /**
     * 
     * The $path property converted to a regular expression, using the $params
     * subpatterns.
     * 
     * @var string
     * 
     */
    protected $regex;

    /**
     * 
     * All param matches found in the path during the `isMatch()` process.
     * 
     * @var array
     * 
     * @see isMatch()
     * 
     */
    protected $matches;

    /**
     * 
     * Retain debugging information about why the route did not match.
     * 
     * @var array
     * 
     */
    protected $debug;

    /**
     * 
     * The name of the wildcard param, if any.
     * 
     * @var array
     * 
     */
    protected $wildcard;
    
    /**
     * 
     * Constructor.
     * 
     * @param string $name The name for this Route.
     * 
     * @param string $path The path for this Route with param token placeholders.
     * 
     * @param array $params Router of param tokens to regex subpatterns.
     * 
     * @param array $values Default values for params.
     * 
     * @param string|array $method The server REQUUEST_METHOD must be one of
     * these values.
     * 
     * @param bool $secure If true, the server must indicate an HTTPS request.
     * 
     * @param bool $routable If true, this Route can be matched; if not, it
     * can be used only to generate a path.
     * 
     * @param callable $is_match A custom callable to evaluate the route.
     * 
     * @param callable $generate A custom callable to generate a path.
     * 
     * @param string $name_prefix A prefix for the name.
     * 
     * @param string $path_prefix A prefix for the path.
     * 
     * @return Route
     * 
     */
    public function __construct(
        $name        = null,
        $path        = null,
        $params      = null,
        $values      = null,
        $method      = null,
        $secure      = null,
        $wildcard    = null,
        $routable    = true,
        $is_match    = null,
        $generate    = null,
        $name_prefix = null,
        $path_prefix = null
    ) {
        // set the name, with prefix if needed
        $this->name_prefix = (string) $name_prefix;
        if ($name_prefix && $name) {
            $this->name = (string) $name_prefix . $name;
        } else {
            $this->name = (string) $name;
        }

        // set the path, with prefix if needed
        $this->path_prefix = (string) $path_prefix;
        if ($path_prefix && strpos($path, '://') === false) {
            // concat the prefix and path
            $this->path = (string) $path_prefix . $path;
            // convert all // to /, so that prefixes ending with / do not mess
            // with paths starting with /
            $this->path = str_replace('//', '/', $this->path);
        } else {
            // no path prefix, or path has :// in it
            $this->path = (string) $path;
        }

        // other properties
        $this->params      = (array) $params;
        $this->values      = (array) $values;
        $this->method      = ($method === null) ? null : (array) $method;
        $this->secure      = ($secure === null) ? null : (bool)  $secure;
        $this->wildcard    = $wildcard;
        $this->routable    = (bool) $routable;
        $this->is_match    = $is_match;
        $this->generate    = $generate;

        // convert path and params to a regular expression
        $this->setRegex();
    }

    /**
     * 
     * Magic read-only for all properties.
     * 
     * @param string $key The property to read from.
     * 
     * @return mixed
     * 
     */
    public function __get($key)
    {
        return $this->$key;
    }

    /**
     * 
     * Magic isset() for all properties.
     * 
     * @param string $key The property to check if isset().
     * 
     * @return bool
     * 
     */
    public function __isset($key)
    {
        return isset($this->$key);
    }

    /**
     * 
     * Checks if a given path and server values are a match for this
     * Route.
     * 
     * @param string $path The path to check against this Route.
     * 
     * @param array $server A copy of $_SERVER so that this Route can check 
     * against the server values.
     * 
     * @return bool
     * 
     */
    public function isMatch($path, array $server)
    {
        if (! $this->routable) {
            $this->debug[] = 'Not routable.';
            return false;
        }

        $is_match = $this->isRegexMatch($path)
                 && $this->isMethodMatch($server)
                 && $this->isSecureMatch($server)
                 && $this->isCustomMatch($server);

        if (! $is_match) {
            return false;
        }

        // populate the path matches into the route values
        foreach ($this->matches as $key => $val) {
            if (is_string($key)) {
                $this->values[$key] = rawurldecode($val);
            }
        }

        // is a wildcard param specified?
        if ($this->wildcard) {
            // are there are actual wildcard values?
            if (empty($this->values[$this->wildcard])) {
                // no, set a blank array
                $this->values[$this->wildcard] = array();
            } else {
                // yes, retain and rawurldecode them
                $this->values[$this->wildcard] = array_map(
                    'rawurldecode',
                    explode('/', $this->values[$this->wildcard])
                );
            }
        }
        
        // done!
        return true;
    }

    /**
     * 
     * Gets the path for this Route with data replacements for param tokens.
     * 
     * @param array $data An array of key-value pairs to interpolate into the
     * param tokens in the path for this Route. Keys that do not map to
     * params are discarded; param tokens that have no mapped key are left in
     * place.
     * 
     * @return string
     * 
     * @todo Make this work with wildcards and optional params.
     * 
     */
    public function generate(array $data = array())
    {
        // the base link template
        $link = $this->path;
        
        // the data for replacements
        $data = array_merge($this->values, $data);
        
        // use a callable to modify the path data?
        if ($this->generate) {
            $data = call_user_func($this->generate, $this, (array) $data);
        }
        
        // replacements for single tokens
        $repl = array();
        foreach ($data as $key => $val) {
            // encode the single value
            if (is_scalar($val) || $val === null) {
                $repl["{{$key}}"] = rawurlencode($val);
            }
        }
        
        // replacements for optional params, if any
        preg_match('#{/([a-zA-Z0-9_,]+)}#', $link, $matches);
        if ($matches) {
            // this is the full token to replace in the link
            $key = $matches[0];
            // start with an empty replacement
            $repl[$key] = '';
            // the optional param names in the token
            $names = explode(',', $matches[1]);
            // look for data for each of the param names
            foreach ($names as $name) {
                // is there data for this optional param?
                if (! isset($data[$name])) {
                    // options are *sequentially* optional, so if one is
                    // missing, we're done
                    break;
                }
                // encode the optional value
                if (is_scalar($data[$name])) {
                    $repl[$key] .= '/' . rawurlencode($data[$name]);
                }
            }
        }
        
        // replace params in the link, including optional params
        $link = strtr($link, $repl);
        
        // add wildcard data
        if ($this->wildcard && isset($data[$this->wildcard])) {
            $link = rtrim($link, '/');
            foreach ($data[$this->wildcard] as $val) {
                // encode the wildcard value
                if (is_scalar($val)) {
                    $link .= '/' . rawurlencode($val);
                }
            }
        }
        
        // done!
        return $link;
    }

    /**
     * 
     * Sets the regular expression for this Route.
     * 
     * @return null
     * 
     */
    protected function setRegex()
    {
        $this->regex = $this->path;
        $this->setRegexOptionalParams();
        $this->setRegexParams();
        $this->setRegexWildcard();
    }

    /**
     * 
     * Expands optional params in the regex from ``{/foo,bar,baz}` to
     * `(/{foo}(/{bar}(/{baz})?)?)?`.
     * 
     * @return null
     * 
     */
    protected function setRegexOptionalParams()
    {
        preg_match('#{/([a-zA-Z0-9_,]+)}#', $this->regex, $matches);
        if (! $matches) {
            return;
        }
        
        $list = explode(',', $matches[1]);
        $head = '';
        $tail = '';
        foreach ($list as $name) {
            $head .= "(/{{$name}}";
            $tail .= ')?';
        }
        $repl = $head . $tail;
        $this->regex = str_replace($matches[0], $repl, $this->regex);
    }
    
    /**
     * 
     * Expands param names in the regex to named subpatterns.
     * 
     * @return null
     * 
     */
    protected function setRegexParams()
    {
        $find = '#{([a-zA-Z0-9_]+)}#';
        preg_match_all($find, $this->regex, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = $match[1];
            $subpattern = $this->getSubpattern($name);
            $this->regex = str_replace("{{$name}}", $subpattern, $this->regex);
            if (! array_key_exists($name, $this->values)) {
                $this->values[$name] = null;
            }
        }
    }
    
    /**
     * 
     * Adds a wildcard subpattern to the end of the regex.
     * 
     * @return null
     * 
     */
    protected function setRegexWildcard()
    {
        if (! $this->wildcard) {
            return;
        }
        
        $this->regex = rtrim($this->regex, '/')
                     . "(/(?P<{$this->wildcard}>.*))?";
    }
    
    /**
     * 
     * Returns a named subpattern for a param name.
     * 
     * @param string $name The param name.
     * 
     * @return string The named subpattern.
     * 
     */
    protected function getSubpattern($name)
    {
        // is there a custom subpattern for the name?
        if (isset($this->params[$name])) {
            // use a custom subpattern
            $subpattern = $this->params[$name];
            if ($subpattern[0] != '(') {
                $message = "Subpattern for param '$name' must start with '('.";
                throw new Exception\MalformedSubpattern($message);
            }
            return "(?P<$name>" . substr($subpattern, 1);
        }
        
        // use a default subpattern
        return "(?P<$name>[^/]+)";
    }
    
    /**
     * 
     * Checks that the path matches the Route regex.
     * 
     * @param string $path The path to match against.
     * 
     * @return bool True on a match, false if not.
     * 
     */
    protected function isRegexMatch($path)
    {
        $regex = "#^{$this->regex}$#";
        $match = preg_match($regex, $path, $this->matches);
        if (! $match) {
            $this->debug[] = 'Not a regex match.';
        }
        return $match;
    }

    /**
     * 
     * Checks that the Route `$method` matches the corresponding server value.
     * 
     * @param array $server A copy of $_SERVER.
     * 
     * @return bool True on a match, false if not.
     * 
     */
    protected function isMethodMatch($server)
    {
        if (isset($this->method)) {
            if (! isset($server['REQUEST_METHOD'])) {
                $this->debug[] = 'Method match requested but REQUEST_METHOD not set.';
                return false;
            }
            if (! in_array($server['REQUEST_METHOD'], $this->method)) {
                $this->debug[] = 'Not a method match.';
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * Checks that the Route `$secure` matches the corresponding server values.
     * 
     * @param array $server A copy of $_SERVER.
     * 
     * @return bool True on a match, false if not.
     * 
     */
    protected function isSecureMatch($server)
    {
        if ($this->secure !== null) {

            $is_secure = (isset($server['HTTPS']) && $server['HTTPS'] == 'on')
                      || (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] == 443);

            if ($this->secure == true && ! $is_secure) {
                $this->debug[] = 'Secure required, but not secure.';
                return false;
            }

            if ($this->secure == false && $is_secure) {
                $this->debug[] = 'Non-secure required, but is secure.';
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * Checks that the custom Route `$is_match` callable returns true, given 
     * the server values.
     * 
     * @param array $server A copy of $_SERVER.
     * 
     * @return bool True on a match, false if not.
     * 
     */
    protected function isCustomMatch($server)
    {
        if (! $this->is_match) {
            return true;
        }

        // pass the matches as an object, not as an array, so we can avoid
        // tricky hacks for references
        $matches = new ArrayObject($this->matches);
        $result = call_user_func($this->is_match, $server, $matches);

        // convert back to array
        $this->matches = $matches->getArrayCopy();

        // did it match?
        if (! $result) {
            $this->debug[] = 'Not a custom match.';
        }

        return $result;
    }
}
