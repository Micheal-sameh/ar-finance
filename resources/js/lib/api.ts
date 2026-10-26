import axios from 'axios';

const api = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

let pageTenantId: number | null = null;

/**
 * The service the current page is acting in, when a Platform Admin is on a
 * record's page without having switched into its service (see
 * TenantContext). Picker lookups carry it as `for_tenant` so they list that
 * service's accounts, not every service's.
 */
export function setApiTenant(tenantId: number | null) {
    pageTenantId = tenantId;
}

api.interceptors.request.use((config) => {
    if (pageTenantId !== null && config.params?.for_tenant === undefined) {
        config.params = { ...config.params, for_tenant: pageTenantId };
    }

    return config;
});

export default api;
