import axios from 'axios';

const api = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

export default api;
