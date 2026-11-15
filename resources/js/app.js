import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
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
