import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import bwipjs from 'bwip-js';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Barcode,
    Building2,
    ChartNoAxesCombined,
    Coins,
    Gem,
    Hammer,
    LayoutDashboard,
    Package,
    PiggyBank,
    ReceiptText,
    RefreshCcw,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Truck,
    Users,
    UsersRound,
    Wallet,
} from 'lucide-react';

window.Alpine = Alpine;
window.Chart = Chart;
window.bwipjs = bwipjs;

window.inventoryLabels = () => ({
    routes: {},
    selected: [],
    filteredCount: 0,
    template: 'gold',
    paperType: 'a4',
    width: 50,
    height: 25,
    margin: 6,
    gap: 3,
    orientation: 'portrait',
    copies: 1,
    includeQr: false,
    fields: ['weights', 'huid', 'metal', 'purity'],
    notice: '',

    init() {
        this.routes = this.$el.dataset;
        this.filteredCount = Number(this.$el.dataset.filteredCount || 0);
    },
    visibleIds() {
        return Array.from(document.querySelectorAll('[data-inventory-id]'))
            .map((row) => row.dataset.inventoryId);
    },
    toggleVisible(checked) {
        const visible = this.visibleIds();
        this.selected = checked
            ? [...new Set([...this.selected, ...visible])]
            : this.selected.filter((id) => !visible.includes(String(id)));
    },
    selectUntagged() {
        this.selected = Array.from(document.querySelectorAll('[data-inventory-id][data-untagged="1"]'))
            .map((row) => row.dataset.inventoryId);
        this.notice = `${this.selected.length} untagged item(s) selected on this page.`;
    },
    setPreset(value) {
        if (value === 'custom') return;
        const [width, height] = value.split('x').map(Number);
        this.width = width;
        this.height = height;
    },
    appendHidden(form, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    },
    appendFilters(form) {
        const filters = new FormData(document.getElementById('label-filters'));
        for (const [name, value] of filters.entries()) {
            if (value) this.appendHidden(form, name, value);
        }
    },
    appendPrintOptions(form) {
        this.appendHidden(form, 'template', this.template);
        this.appendHidden(form, 'paper_type', this.paperType);
        this.appendHidden(form, 'width_mm', this.width);
        this.appendHidden(form, 'height_mm', this.height);
        this.appendHidden(form, 'margin_mm', this.paperType === 'a4' ? this.margin : 0);
        this.appendHidden(form, 'gap_mm', this.paperType === 'a4' ? this.gap : 0);
        this.appendHidden(form, 'orientation', this.orientation);
        this.appendHidden(form, 'copies', this.copies);
        if (this.includeQr || this.template === 'qr') this.appendHidden(form, 'include_qr', '1');
        for (const field of this.fields) this.appendHidden(form, 'fields[]', field);
    },
    submit(action, allFiltered) {
        if (!allFiltered && this.selected.length === 0) {
            this.notice = 'Select one or more inventory items first.';
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action === 'barcode' ? this.routes.generateBarcode
            : action === 'qr' ? this.routes.generateQr : this.routes.print;
        this.appendHidden(form, '_token', document.querySelector('meta[name="csrf-token"]').content);
        if (allFiltered) {
            this.appendHidden(form, 'all_filtered', '1');
            this.appendFilters(form);
        } else {
            for (const id of this.selected) this.appendHidden(form, 'ids[]', id);
        }
        if (action === 'print') this.appendPrintOptions(form);
        document.body.appendChild(form);
        form.submit();
        form.remove();
    },
    preview(id = null) {
        const ids = id ? [String(id)] : this.selected;
        if (ids.length === 0) {
            this.notice = 'Select an item to preview its tag.';
            return;
        }

        const form = document.createElement('form');
        form.method = 'GET';
        form.action = this.routes.preview;
        form.target = '_blank';
        for (const itemId of ids) this.appendHidden(form, 'ids[]', itemId);
        this.appendPrintOptions(form);
        document.body.appendChild(form);
        form.submit();
        form.remove();
    },
});

document.querySelectorAll('[data-barcode-canvas]').forEach((canvas) => {
    try {
        window.bwipjs.toCanvas(canvas, {
            bcid: canvas.dataset.barcodeFormat === 'ean13' ? 'ean13' : 'code128',
            text: canvas.dataset.barcodeValue,
            scale: 2,
            height: 8,
            includetext: true,
            textxalign: 'center',
            padding: 1,
        });
    } catch (error) {
        canvas.hidden = true;
        const message = canvas.parentElement.querySelector('[data-code-error]');
        message.hidden = false;
        message.textContent = `Barcode could not be rendered: ${error.message}`;
    }
});

document.querySelectorAll('[data-qr-canvas]').forEach((canvas) => {
    try {
        window.bwipjs.toCanvas(canvas, {
            bcid: 'qrcode',
            text: canvas.dataset.qrValue,
            scale: 3,
            eclevel: 'M',
            padding: 1,
        });
    } catch (error) {
        canvas.hidden = true;
        const message = canvas.parentElement.querySelector('[data-code-error]');
        message.hidden = false;
        message.textContent = `QR code could not be rendered: ${error.message}`;
    }
});

Alpine.start();

const sidebarIcons = {
    Barcode,
    Building2,
    ChartNoAxesCombined,
    Coins,
    Gem,
    Hammer,
    LayoutDashboard,
    Package,
    PiggyBank,
    ReceiptText,
    RefreshCcw,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Truck,
    Users,
    UsersRound,
    Wallet,
};

document.querySelectorAll('[data-sidebar-icon]').forEach((element) => {
    const Icon = sidebarIcons[element.dataset.sidebarIcon];

    if (!Icon) {
        throw new Error(`Unknown sidebar icon: ${element.dataset.sidebarIcon}`);
    }

    createRoot(element).render(createElement(Icon, {
        size: 19,
        strokeWidth: 1.9,
        'aria-hidden': true,
        focusable: false,
    }));
});
