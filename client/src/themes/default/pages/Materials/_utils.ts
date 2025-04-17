import parseInteger from '@/utils/parseInteger';
import isValidInteger from '@/utils/isValidInteger';
import { UNCATEGORIZED } from '@/stores/api/materials';

import type { Route } from 'vue-router';
import type { Filters } from './components/Filters';

export const getFiltersFromRoute = (route: Route): Partial<Filters> | undefined => {
    const routeQuery = route?.query ?? {};
    const filters: Partial<Filters> = {};

    // - Recherche textuelle.
    if ('search' in routeQuery && Array.isArray(routeQuery.search)) {
        filters.search = routeQuery.search
            .map((term: string | null) => (term ?? '').trim())
            .filter((term: string) => term !== '');
    }

    // - Catégorie.
    if ('category' in routeQuery) {
        if (routeQuery.category === UNCATEGORIZED) {
            filters.category = UNCATEGORIZED;
        } else if (isValidInteger(routeQuery.category)) {
            filters.category = parseInteger(routeQuery.category)!;
        }
    }

    // - Sous-Catégorie.
    if (
        'subCategory' in routeQuery &&
        isValidInteger(routeQuery.subCategory) &&
        filters.category !== null &&
        filters.category !== UNCATEGORIZED
    ) {
        filters.subCategory = parseInteger(routeQuery.subCategory)!;
    }

    // - Parc.
    if ('park' in routeQuery && isValidInteger(routeQuery.park)) {
        filters.park = parseInteger(routeQuery.park)!;
    }

    // - Tags.
    if ('tags' in routeQuery && typeof routeQuery.tags === 'string') {
        filters.tags = routeQuery.tags.split(',')
            .map((rawFilter: string) => rawFilter.trim())
            .filter((rawFilter: string) => isValidInteger(rawFilter))
            .map((rawFilter: string) => parseInteger(rawFilter)!);
    }

    return Object.keys(filters).length > 0 ? filters : undefined;
};

export const convertFiltersToRouteQuery = (filters: Filters): Route['query'] => {
    const query: Route['query'] = {};

    // - Recherche textuelle.
    if (filters.search.length > 0) {
        query.search = filters.search;
    }

    // - Parc.
    if (filters.park !== null) {
        query.park = filters.park.toString();
    }

    // - Catégorie.
    if (filters.category !== null) {
        query.category = filters.category.toString();
    }

    // - Sous-catégorie
    if (filters.subCategory !== null) {
        query.subCategory = filters.subCategory.toString();
    }

    // - Tags
    if (filters.tags.length > 0) {
        query.tags = filters.tags.join(',');
    }

    return query;
};
