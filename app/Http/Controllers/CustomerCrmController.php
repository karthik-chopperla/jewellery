<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAdvanceTransaction;
use App\Models\CustomerFeedback;
use App\Models\LoyaltyTransaction;
use App\Models\MarketingCampaign;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerCrmController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::withCount('invoices')
            ->withSum('invoices', 'grand_total')
            ->when($request->tier, fn ($q, $t) => $q->where('group_tier', $t))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20);

        $window = $request->get('window', '7days'); // today, 7days, 15days, 30days, this_month
        $eventType = $request->get('event_type', 'all'); // all, birthday, anniversary

        // Collect upcoming events with metadata
        $eventsList = collect();

        if (in_array($eventType, ['all', 'birthday'])) {
            $birthdays = Customer::getUpcomingEvents('birthday', $window);
            foreach ($birthdays as $c) {
                $days = $c->daysUntilEvent('birthday');
                $nextDate = $c->nextEventDate('birthday');
                $badge = $days === 0 ? 'Today!' : ($days === 1 ? 'Tomorrow (1 day)' : "In {$days} days");
                $eventsList->push([
                    'customer' => $c,
                    'type' => 'birthday',
                    'icon' => '🎂',
                    'event_name' => 'Birthday',
                    'date' => $c->dob,
                    'next_date' => $nextDate,
                    'days_until' => $days,
                    'badge' => $badge,
                ]);
            }
        }

        if (in_array($eventType, ['all', 'anniversary'])) {
            $anniversaries = Customer::getUpcomingEvents('anniversary', $window);
            foreach ($anniversaries as $c) {
                $days = $c->daysUntilEvent('anniversary');
                $nextDate = $c->nextEventDate('anniversary');
                $badge = $days === 0 ? 'Today!' : ($days === 1 ? 'Tomorrow (1 day)' : "In {$days} days");
                $eventsList->push([
                    'customer' => $c,
                    'type' => 'anniversary',
                    'icon' => '💍',
                    'event_name' => 'Anniversary',
                    'date' => $c->anniversary,
                    'next_date' => $nextDate,
                    'days_until' => $days,
                    'badge' => $badge,
                ]);
            }
        }

        $upcomingEvents = $eventsList->sortBy('days_until')->values();

        $stats = [
            'today' => Customer::getUpcomingEvents('birthday', 'today')->count() + Customer::getUpcomingEvents('anniversary', 'today')->count(),
            'seven_days' => Customer::getUpcomingEvents('birthday', '7days')->count() + Customer::getUpcomingEvents('anniversary', '7days')->count(),
            'fifteen_days' => Customer::getUpcomingEvents('birthday', '15days')->count() + Customer::getUpcomingEvents('anniversary', '15days')->count(),
            'one_month' => Customer::getUpcomingEvents('birthday', '30days')->count() + Customer::getUpcomingEvents('anniversary', '30days')->count(),
            'this_month' => Customer::getUpcomingEvents('birthday', 'month')->count() + Customer::getUpcomingEvents('anniversary', 'month')->count(),
        ];

        $campaigns = MarketingCampaign::latest()->take(10)->get();
        $feedbacks = CustomerFeedback::with(['customer', 'invoice'])->latest()->take(10)->get();

        return view('crm.index', compact('customers', 'upcomingEvents', 'stats', 'window', 'eventType', 'campaigns', 'feedbacks'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string|unique:customers,phone',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'dob' => 'nullable|date',
            'anniversary' => 'nullable|date',
            'pan_number' => 'nullable|string',
            'group_tier' => 'required|in:standard,silver,gold,vip',
            'credit_limit' => 'nullable|numeric|min:0',
            'preferences' => 'nullable|string',
        ]);

        $next = (Customer::max('id') ?? 0) + 1;
        $data['customer_code'] = 'CUST-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        $customer = Customer::create($data);

        AuditService::log('CRM', 'create', "Registered customer {$customer->name}", $customer);
        return back()->with('success', "Customer {$customer->name} ({$customer->customer_code}) added.");
    }

    public function show(Customer $customer)
    {
        $customer->load(['invoices.items', 'oldGoldPurchases', 'goldSchemes', 'loyaltyTransactions', 'advanceTransactions', 'wishlists.product']);
        return view('crm.show', compact('customer'));
    }

    public function recordAdvance(Customer $customer, Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,upi,card,bank_transfer',
            'reference_no' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:255',
        ]);

        $transaction = DB::transaction(function () use ($customer, $data) {
            $lockedCustomer = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $lockedCustomer->increment('advance_balance', $data['amount']);
            $lockedCustomer->refresh();

            return CustomerAdvanceTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'received_by' => Auth::id(),
                'type' => 'received',
                'amount' => $data['amount'],
                'balance_after' => $lockedCustomer->advance_balance,
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'] ?? 'Customer advance received',
            ]);
        });

        AuditService::log('CRM', 'customer_advance', "Received ₹{$transaction->amount} advance from {$customer->name}", $transaction);

        return back()->with('success', 'Customer advance recorded.');
    }

    public function adjustLoyalty(Customer $customer, Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:earn,redeem,adjust',
            'points' => 'required|integer|min:1',
            'description' => 'required|string',
        ]);

        if ($data['type'] === 'redeem') {
            if ($customer->loyalty_points < $data['points']) {
                return back()->withErrors(['points' => 'Insufficient loyalty points.']);
            }
            $customer->decrement('loyalty_points', $data['points']);
        } else {
            $customer->increment('loyalty_points', $data['points']);
        }

        LoyaltyTransaction::create([
            'customer_id' => $customer->id,
            'points' => $data['points'],
            'type' => $data['type'],
            'description' => $data['description'],
        ]);

        return back()->with('success', 'Loyalty points updated.');
    }

    public function storeCampaign(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'channel' => 'required|in:whatsapp,sms,email',
            'occasion' => 'required|string',
            'target_group' => 'required|string',
            'message' => 'required|string',
        ]);

        $target = $data['target_group'];
        $audienceCount = 0;

        if (str_starts_with($target, 'birthday_')) {
            $win = substr($target, strlen('birthday_'));
            $matching = Customer::getUpcomingEvents('birthday', $win);
            $audienceCount = $matching->count();
        } elseif (str_starts_with($target, 'anniversary_')) {
            $win = substr($target, strlen('anniversary_'));
            $matching = Customer::getUpcomingEvents('anniversary', $win);
            $audienceCount = $matching->count();
        } elseif ($target === 'all') {
            $audienceCount = Customer::count();
        } else {
            $audienceCount = Customer::where('group_tier', $target)->count();
        }

        $campaign = MarketingCampaign::create($data + [
            'status' => 'sent',
            'sent_count' => $audienceCount,
        ]);

        AuditService::log('CRM', 'campaign', "Dispatched campaign '{$data['title']}' to {$audienceCount} customers via {$data['channel']}", $campaign);

        return back()->with('success', "Campaign '{$data['title']}' dispatched to {$audienceCount} target customers via {$data['channel']}.");
    }

    public function sendDirectGreeting(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'occasion' => 'required|string',
            'channel' => 'required|in:whatsapp,sms,email',
            'message' => 'required|string',
        ]);

        $customer = Customer::findOrFail($data['customer_id']);

        $campaign = MarketingCampaign::create([
            'title' => "Direct {$data['occasion']} Greeting — {$customer->name}",
            'channel' => $data['channel'],
            'occasion' => strtolower($data['occasion']),
            'target_group' => $customer->group_tier,
            'message' => $data['message'],
            'status' => 'sent',
            'sent_count' => 1,
        ]);

        AuditService::log('CRM', 'direct_greeting', "Sent {$data['occasion']} greeting to {$customer->name} ({$customer->phone}) via {$data['channel']}", $campaign);

        return back()->with('success', "Greeting sent to {$customer->name} via {$data['channel']}!");
    }

    public function destroyCustomer(Customer $customer)
    {
        $name = $customer->name;
        $customer->delete();

        AuditService::log('CRM', 'delete_customer', "Deleted customer {$name}");
        return back()->with('success', "Customer {$name} deleted successfully.");
    }

    public function destroyCampaign(MarketingCampaign $campaign)
    {
        $title = $campaign->title;
        $campaign->delete();

        AuditService::log('CRM', 'delete_campaign', "Deleted campaign {$title}");
        return back()->with('success', "Campaign {$title} deleted successfully.");
    }
}

