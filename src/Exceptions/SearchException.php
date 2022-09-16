<?php

namespace CodeDistortion\Stepwise\Exceptions;

/**
 * Stepwise exceptions caused by the searcher
 */
class SearchException extends StepwiseException
{
    /**
     * Build an exception to throw when a search has NOT been run
     *
     * @param string $method The method being called.
     * @return static
     */
    public static function searchIsPending(string $method): self
    {
        return new static(
            'Method '.$method.'(..) cannot be called as the search is pending (the search has not been run yet)'
        );
    }
    /**
     * Build an exception to throw when a search HAS been run
     *
     * @param string $method The method being called.
     * @return static
     */
    public static function searchIsNotPending(string $method): self
    {
        return new static(
            'Method '.$method.'(..) cannot be called as the search is not pending (the search has already been run)'
        );
    }

    /**
     * Build an exception to throw when trying to do something but test-mode is off
     *
     * @param string $method The method being called.
     * @return static
     */
    public static function notInTestMode(string $method): self
    {
        return new static('Method '.$method.'(..) can only be called when using test-mode');
    }



    /**
     * Build an exception to throw when a tag was requested but doesn't exist
     *
     * @param string $tagName The tag that doesn't exist.
     * @return static
     */
    public static function filterTagDoesNotExist(string $tagName): self
    {
        return new static('Filter-tag "'.$tagName.'" doesn\'t exist');
    }

    /**
     * Build an exception to throw when a filter is not found
     *
     * @param string $filterName The invalid filter.
     * @return static
     */
    public static function filterNotFound(string $filterName): self
    {
        return new static('The filter "'.$filterName.'" doesn\'t exist');
    }

    /**
     * Build an exception to throw when a filter-alias is invalid
     *
     * @param string $filterAlias The invalid filter alias.
     * @return static
     */
    public static function invalidFilterAlias(string $filterAlias): self
    {
        return new static('The filter-alias must start with a \'+\' or \'-\'. "'.$filterAlias.'" was found');
    }

    /**
     * Build an exception to throw when a filter-method gives an invalid action-check response
     *
     * @param string $class            The class the filter-method is in.
     * @param string $filterMethod     The filter-method that was called.
     * @param string $actionCheckParam The the name of the action-check-parameter.
     * @return static
     */
    public static function invalidFilterActionCheckResponse(
        string $class,
        string $filterMethod,
        string $actionCheckParam
    ): self {

        return new static(
            'The '.$class.'::'.$filterMethod.'(..) method '
            .'must return '
            .'[\'alter\' => true/false, \'track\' => [fields...], \'link\' => [fields...]] (or false) '
            .'when $'.$actionCheckParam.' is true'
        );
    }

    /**
     * Build an exception to throw when a tag doesn't contain any filters
     *
     * @param string $tagName The tag which has no filters.
     * @return static
     */
    public static function tagGroupHasNoFilters(string $tagName): self
    {
        return new static('The tagGroup "'.$tagName.'" doesn\'t have any applicable filters');
    }

    /**
     * Build an exception to throw when no FilterReference object has been created yet
     *
     * @return static
     */
    public static function noFilterReference(): self
    {
        return new static('No filter-reference has been created yet');
    }

    /**
     * Build an exception to throw when a to-be-tracked field hasn't been defined
     *
     * @param string $field The name of the undefined field to be tracked.
     * @return static
     */
    public static function untrackableField(string $field): self
    {
        return new static('Cannot track field "'.$field.'" - it has not been defined');
    }

    /**
     * Build an exception to throw when a clause could not be used
     *
     * @return static
     */
    public static function unknownClause(): self
    {
        return new static('Unknown Clause given');
    }

    /**
     * Build an exception to throw when the given order-by is invalid
     *
     * @param string $orderBy The order-by being accessed.
     * @return static
     */
    public static function invalidOrderBy(string $orderBy): self
    {
        return new static('The given "'.$orderBy.'" is not a field or an order-by alias');
    }

    /**
     * Build an exception to throw when refTable could not be resolved
     *
     * @param string $refTable The refTable that could not be resolved.
     * @return static
     */
    public static function missingRefTableClass(string $refTable): self
    {
        return new static('RefTable class "'.$refTable.'" does not exist');
    }
}
