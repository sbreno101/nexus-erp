/**
 * JavaScript da aplicação.
 *
 * O Livewire 4 já inclui o Alpine.js; aqui apenas registramos componentes
 * Alpine reutilizáveis (evento "alpine:init") e o Chart.js para os gráficos.
 */
import Chart from 'chart.js/auto';

window.Chart = Chart;

const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/** Converte "1.234,56" em centavos (inteiro), sem usar float. */
function parseMoneyToCents(value) {
    const digits = String(value ?? '').replace(/\D/g, '');

    return digits === '' ? 0 : parseInt(digits, 10);
}

/** Formata centavos como "1.234,56". */
function formatCents(cents) {
    const negative = cents < 0;
    const absolute = Math.abs(cents);
    const integer = Math.floor(absolute / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const decimal = String(absolute % 100).padStart(2, '0');

    return `${negative ? '-' : ''}${integer},${decimal}`;
}

window.NexusMoney = { parseMoneyToCents, formatCents, formatBRL: (cents) => brl.format(cents / 100) };

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /** Tema claro/escuro lembrado no navegador. */
    Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try {
                localStorage.setItem('nexus-theme', this.dark ? 'dark' : 'light');
            } catch (e) {
                // Armazenamento indisponível: o tema vale apenas para esta página.
            }
            window.dispatchEvent(new CustomEvent('theme-changed'));
        },
    });

    /** Campo monetário com máscara brasileira: o usuário digita só números. */
    Alpine.data('moneyInput', (initial = '') => ({
        display: initial,
        init() {
            if (this.display !== '') {
                this.display = formatCents(parseMoneyToCents(this.display));
            }
        },
        onInput(event) {
            const cents = parseMoneyToCents(event.target.value);
            this.display = cents === 0 && event.target.value.replace(/\D/g, '') === '' ? '' : formatCents(cents);
        },
    }));

    /** Notificações temporárias (toasts). Disparadas por sessão ou pelo evento "notify". */
    Alpine.data('toasts', (initial = []) => ({
        items: [],
        init() {
            initial.forEach((toast) => this.add(toast));
        },
        add({ type = 'success', message = '' }) {
            const id = Date.now() + Math.random();
            this.items.push({ id, type, message });
            setTimeout(() => this.remove(id), 6000);
        },
        remove(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    }));

    /**
     * Editor de itens de proposta/contrato. Calcula uma PRÉVIA dos totais no
     * navegador; o valor oficial é sempre recalculado no servidor.
     */
    Alpine.data('lineItemsEditor', ({ items = [], catalog = [], discount = '0,00' }) => ({
        items: items.length ? items : [],
        catalog,
        discount,
        init() {
            if (this.items.length === 0) {
                this.addItem();
            }
        },
        addItem() {
            this.items.push({ key: Date.now() + Math.random(), catalog_item_id: '', description: '', quantity: '1', unit: 'UN', unit_price: '', discount: '' });
        },
        removeItem(index) {
            this.items.splice(index, 1);
            if (this.items.length === 0) {
                this.addItem();
            }
        },
        selectCatalog(item) {
            const product = this.catalog.find((entry) => String(entry.id) === String(item.catalog_item_id));
            if (product) {
                item.description = product.name;
                item.unit = product.unit;
                item.unit_price = formatCents(product.price_cents);
            }
        },
        quantityValue(item) {
            const normalized = String(item.quantity ?? '0').replace(/\./g, '').replace(',', '.');
            const parsed = Number(normalized);

            return Number.isFinite(parsed) ? parsed : 0;
        },
        itemTotal(item) {
            const gross = Math.round(parseMoneyToCents(item.unit_price) * this.quantityValue(item));

            return Math.max(0, gross - parseMoneyToCents(item.discount));
        },
        get subtotal() {
            return this.items.reduce((sum, item) => sum + this.itemTotal(item), 0);
        },
        get total() {
            return Math.max(0, this.subtotal - parseMoneyToCents(this.discount));
        },
        format(cents) {
            return brl.format(cents / 100);
        },
        formatMoneyField(item, field) {
            item[field] = item[field] === '' ? '' : formatCents(parseMoneyToCents(item[field]));
        },
    }));

    /**
     * Gráfico Chart.js. Recriado quando o Livewire troca o elemento
     * (o wire:key muda junto com os dados).
     */
    Alpine.data('chart', (config) => ({
        instance: null,
        init() {
            this.render();
            window.addEventListener('theme-changed', () => this.render());
        },
        render() {
            this.instance?.destroy();
            const dark = document.documentElement.classList.contains('dark');
            const gridColor = dark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(148, 163, 184, 0.25)';
            const textColor = dark ? '#cbd5e1' : '#475569';
            const isMoney = config.money === true;

            this.instance = new Chart(this.$refs.canvas, {
                type: config.type,
                data: config.data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: textColor, boxWidth: 12 } },
                        tooltip: {
                            callbacks: isMoney
                                ? { label: (ctx) => `${ctx.dataset.label ?? ctx.label}: ${brl.format((ctx.raw ?? 0) / 100)}` }
                                : {},
                        },
                    },
                    scales: ['doughnut', 'pie'].includes(config.type)
                        ? {}
                        : {
                            x: { ticks: { color: textColor }, grid: { color: gridColor } },
                            y: {
                                beginAtZero: true,
                                ticks: { color: textColor, callback: (value) => (isMoney ? brl.format(value / 100) : value) },
                                grid: { color: gridColor },
                            },
                        },
                },
            });
        },
        destroy() {
            this.instance?.destroy();
        },
    }));
});
