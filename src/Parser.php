<?php

namespace Lucid;

use Lucid\Entities\Domain;
use Lucid\Entities\Feature;
use Lucid\Entities\Action;

class Parser
{
    use Finder;

    const SYNTAX_STRING = 'string';

    const SYNTAX_KEYWORD = 'keyword';

    const SYNTAX_INSTANTIATION = 'init';

    /**
     * Get the list of actions for the given feature.
     */
    public function parseFeatureActions(Feature $feature): array
    {
        $contents = file_get_contents($feature->realPath);

        $body = explode("\n", $this->parseFunctionBody($contents, 'handle'));

        $actions = [];
        foreach ($body as $line) {
            $action = $this->parseActionInLine($line, $contents);
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    public function parseFunctionBody($contents, $function): string
    {
        // $pattern = "/function\s$function\([a-zA-Z0-9_\$\s,]+\)?". // match "function handle(...)"
        //     '[\n\s]?[\t\s]*'. // regardless of the indentation preceding the {
        //     '{([^{}]*)}/'; // find everything within braces.

        $pattern = '~^\s*[\w\s]+\(.*\)\s*\K({((?>"[^"]*+"|\'[^\']*+\'|//.*$|/\*[\s\S]*?\*/|#.*$|<<<\s*["\']?(\w+)["\']?[^;]+\3;$|[^{}<\'"/#]++|[^{}]++|(?1))*)})~m';

        // '~^ \s* [\w\s]+ \( .* \) \s* \K'.       # how it matches a function definition
        //      '('.                                  # (1 start)
        //           '{'.                             # opening brace
        //           '('.                             # (2 start)
        /*                '(?>'.*/                      // atomic grouping (for its non-capturing purpose only)
        //                     '" [^"]*+ "'.          # double quoted strings
        //                  '|  \' [^\']*+ \''.       # single quoted strings
        //                  '|  // .* $'.             # a comment block starting with //
        //                  '|  /\* [\s\S]*? \*/'.    # a multi line comment block /*...*/
        //                  '|  \# .* $'.             # a single line comment block starting with #...
        //                  '|  <<< \s* ["\']?'.      # heredocs and nowdocs
        //                     '( \w+ )'.             # (3) ^
        //                     '["\']? [^;]+ \3 ; $'. # ^
        //                  '|  [^{}<\'"/#]++'.       # force engine to backtack if it encounters special characters [<'"/#] (possessive)
        //                  '|  [^{}]++'.             # default matching bahaviour (possessive)
        //                  '|  (?1)'.                # recurse 1st capturing group
        //                ')*'.                       # zero to many times of atomic group
        //           ')'.                             # (2 end)
        //           '}'.                             # closing brace
        //      ')~';                                  # (1 end)

        preg_match($pattern, $contents, $match);

        return $match[1];
    }

    /**
     * Parses the action class out of the given line of code.
     *
     * @throws \Exception
     */
    public function parseActionInLine(string $line, string $contents): ?Action
    {
        $line = trim($line);
        // match the line that potentially has the action,
        // they're usually called by "$this->run(Action...)"
        preg_match('/->run\(([^,]*),?.*\)?/i', $line, $match);

        // we won't do anything if no action has been matched.
        if (empty($match)) {
            return null;
        }

        $match = $match[1];
        // prepare for parsing
        $match = $this->filterActionMatch($match);

        $name = $namespace = '';

        /*
        * determine syntax style and afterwards detect how the action
        * class name was put into the "run" method as a parameter.
        *
        * Following are the different ways this might occur:
        *
        * 	- ValidateArticleInputAction::class
        * 		The class name has been imported with a 'use' statement
        * 		and uses the ::class keyword.
        * 	- \Fully\Qualified\Namespace::class
        * 		Using the ::class keyword with a FQDN.
        * 	- 'Fully\Qualified\Namespace'
        * 		Using a string as a class name with FQDN.
        * 	- new \Full\Class\Namespace
        * 		Instantiation with FQDN
        * 	- new ImportedClass($input)
        * 		Instantiation with an imported class using a `use` statement
        * 		passing parameters to the construction of the instance.
        * 	- new ImportedClass
        * 		Instantiation without parameters nor parentheses.
        */
        switch ($this->actionSyntaxStyle($match)) {
            case self::SYNTAX_STRING:
                [$name, $namespace] = $this->parseStringActionSyntax($match, $contents);
                break;

            case self::SYNTAX_KEYWORD:
                [$name, $namespace] = $this->parseKeywordActionSyntax($match, $contents);
                break;

            case self::SYNTAX_INSTANTIATION:
                [$name, $namespace] = $this->parseInitActionSyntax($match, $contents);
                break;
        }

        $domainName = $this->domainForAction($namespace);

        $domain = new Domain(
            $domainName,
            $this->findDomainNamespace($domainName),
            $domainPath = $this->findDomainPath($domainName),
            $this->relativeFromReal($domainPath)
        );

        $path = $this->findActionPath($domainName, $name);

        return new Action(
            $name,
            $namespace,
            basename($path),
            $path,
            $this->relativeFromReal($path),
            $domain
        );
    }

    /**
     * Parse the given action class written in the string syntax: 'Some\Domain\Action'
     */
    private function parseStringActionSyntax(string $match, string $contents): array
    {
        $slash = strrpos($match, '\\');
        if ($slash !== false) {
            $name = str_replace('\\', '', Str::substr($match, $slash));
            $namespace = '\\'.preg_replace('/^\\\/', '', $match);

            return [$name, $namespace];
        }

        return ['', ''];
    }

    /**
     * Parse the given action class written in the ::class keyword syntax:	SomeAction::class
     */
    private function parseKeywordActionSyntax(string $match, string $contents): array
    {
        // is it of the form \Full\Name\Space::class?
        // (using full namespace in-line)
        // to figure that out we look for
        // the last occurrence of a \
        $slash = strrpos($match, '\\');
        if ($slash !== false) {
            $namespace = str_replace('::class', '', $match);
            // remove the ::class and the \ prefix
            $name = str_replace(['\\', '::class'], '', Str::substr($namespace, $slash));
        } else {
            // nope it's just Space::class, we will figure
            // out the namespace from a "use" statement.
            $name = str_replace(['::class', ');'], '', $match);
            preg_match("/use\s(.*$name)/", $contents, $namespace);
            // it is necessary to have a \ at the beginning.
            $namespace = '\\'.preg_replace('/^\\\/', '', $namespace[1]);
        }

        return [$name, $namespace];
    }

    /**
     * Parse the given action class written in the ini syntax:	new SomeAction()
     */
    private function parseInitActionSyntax(string $match, string $contents): array
    {
        // remove the 'new ' from the beginning.
        $match = str_replace('new ', '', $match);

        // match the action's class name
        preg_match('/(.*Action).*[\);]?/', $match, $name);
        $name = $name[1];

        // Determine Namespace
        $slash = strrpos($name, '\\');
        // when there's a slash when matching the reverse of the namespace,
        // it is considered to be the full namespace we have.
        if ($slash !== false) {
            $namespace = $name;
            // prefix with a \ if not found.
            $name = str_replace('\\', '', Str::substr($namespace, $slash));
        } else {
            // we don't have the full namespace, so we will figure it out
            // from the 'use' statements that we have in the file.
            preg_match("/use\s(.*$name)/", $contents, $namespace);
            $namespace = '\\'.preg_replace('/^\\\/', '', $namespace[1]);
        }

        return [$name, $namespace];
    }

    /**
     * Get the domain for the given action's namespace.
     */
    private function domainForAction(string $namespace): string
    {
        preg_match('/Domains\\\([^\\\]*)\\\Actions/', $namespace, $domain);

        return (! empty($domain)) ? $domain[1] : '';
    }

    /**
     * Filter the matched line in preparation for parsing.
     */
    private function filterActionMatch(string $match): string
    {
        // we don't want any quotes
        return str_replace(['"', "'"], '', $match);
    }

    /**
     * Determine the syntax style of the class name.
     * There are three styles possible:
     *
     * 	- Using the 'TheAction::class' keyword
     * 	- Using instantiation: new TheAction(...)
     * 	- Using a string with the full namespace: '\Domain\TheAction'
     */
    private function actionSyntaxStyle(string $match): string
    {
        if (str_contains($match, '::class')) {
            $style = self::SYNTAX_KEYWORD;
        } elseif (str_contains($match, 'new ')) {
            $style = self::SYNTAX_INSTANTIATION;
        } else {
            $style = self::SYNTAX_STRING;
        }

        return $style;
    }
}
