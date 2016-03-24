<?php namespace App\Models;

use Utils;
use DateTime;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends EntityModel
{
    use SoftDeletes {
        SoftDeletes::trashed as parentTrashed;
    }

    protected $dates = ['deleted_at'];

    protected $casts = [
        'is_recurring' => 'boolean',
        'has_tasks' => 'boolean',
        'auto_bill' => 'boolean',
    ];

    public static $patternFields = [
        'counter',
        'custom1',
        'custom2',
        'userId',
        'year',
        'date:',
    ];
    
    public function trashed()
    {
        if ($this->client && $this->client->trashed()) {
            return true;
        }

        return self::parentTrashed();
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    public function invoice_items()
    {
        return $this->hasMany('App\Models\InvoiceItem')->orderBy('id');
    }

    public function invoice_status()
    {
        return $this->belongsTo('App\Models\InvoiceStatus');
    }

    public function invoice_design()
    {
        return $this->belongsTo('App\Models\InvoiceDesign');
    }

    public function recurring_invoice()
    {
        return $this->belongsTo('App\Models\Invoice');
    }

    public function recurring_invoices()
    {
        return $this->hasMany('App\Models\Invoice', 'recurring_invoice_id');
    }

    public function invitations()
    {
        return $this->hasMany('App\Models\Invitation')->orderBy('invitations.contact_id');
    }

    public function getName()
    {
        return $this->is_recurring ? trans('texts.recurring') : $this->invoice_number;
    }

    public function getFileName()
    {
        $entityType = $this->getEntityType();
        return trans("texts.$entityType") . '_' . $this->invoice_number . '.pdf';
    }

    public function getPDFPath()
    {
        return storage_path() . '/pdfcache/cache-' . $this->id . '.pdf';
    }

    public static function calcLink($invoice)
    {
        return link_to('invoices/' . $invoice->public_id, $invoice->invoice_number);
    }

    public function getLink()
    {
        return self::calcLink($this);
    }

    public function getEntityType()
    {
        return $this->is_quote ? ENTITY_QUOTE : ENTITY_INVOICE;
    }

    public function isSent()
    {
        return $this->invoice_status_id >= INVOICE_STATUS_SENT;
    }

    public function isViewed()
    {
        return $this->invoice_status_id >= INVOICE_STATUS_VIEWED;
    }

    public function isPaid()
    {
        return $this->invoice_status_id >= INVOICE_STATUS_PAID;
    }

    public function getRequestedAmount()
    {
        return $this->partial > 0 ? $this->partial : $this->balance;
    }

    public function hidePrivateFields()
    {
        $this->setVisible([
            'invoice_number',
            'discount',
            'is_amount_discount',
            'po_number',
            'invoice_date',
            'due_date',
            'terms',
            'invoice_footer',
            'public_notes',
            'amount',
            'balance',
            'invoice_items',
            'client',
            'tax_name',
            'tax_rate',
            'account',
            'invoice_design',
            'invoice_design_id',
            'is_pro',
            'is_quote',
            'custom_value1',
            'custom_value2',
            'custom_taxes1',
            'custom_taxes2',
            'partial',
            'has_tasks',
            'custom_text_value1',
            'custom_text_value2',
        ]);

        $this->client->setVisible([
            'name',
            'id_number',
            'vat_number',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'work_phone',
            'payment_terms',
            'contacts',
            'country',
            'currency_id',
            'custom_value1',
            'custom_value2',
        ]);

        $this->account->setVisible([
            'name',
            'id_number',
            'vat_number',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'work_phone',
            'work_email',
            'country',
            'currency_id',
            'custom_label1',
            'custom_value1',
            'custom_label2',
            'custom_value2',
            'custom_client_label1',
            'custom_client_label2',
            'primary_color',
            'secondary_color',
            'hide_quantity',
            'hide_paid_to_date',
            'custom_invoice_label1',
            'custom_invoice_label2',
            'pdf_email_attachment',
            'show_item_taxes',
            'custom_invoice_text_label1',
            'custom_invoice_text_label2',
        ]);

        foreach ($this->invoice_items as $invoiceItem) {
            $invoiceItem->setVisible([
                'product_key',
                'notes',
                'cost',
                'qty',
                'tax_name',
                'tax_rate',
            ]);
        }

        foreach ($this->client->contacts as $contact) {
            $contact->setVisible([
                'first_name',
                'last_name',
                'email',
                'phone',
            ]);
        }

        return $this;
    }

    public function getSchedule()
    {
        if (!$this->start_date || !$this->is_recurring || !$this->frequency_id) {
            return false;
        }

        $startDate = $this->getOriginal('last_sent_date') ?: $this->getOriginal('start_date');
        $startDate .= ' ' . $this->account->recurring_hour . ':00:00';
        $startDate = $this->account->getDateTime($startDate);
        $endDate = $this->end_date ? $this->account->getDateTime($this->getOriginal('end_date')) : null;
        $timezone = $this->account->getTimezone();

        $rule = $this->getRecurrenceRule();
        $rule = new \Recurr\Rule("{$rule}", $startDate, $endDate, $timezone);

        // Fix for months with less than 31 days
        $transformerConfig = new \Recurr\Transformer\ArrayTransformerConfig();
        $transformerConfig->enableLastDayOfMonthFix();
        
        $transformer = new \Recurr\Transformer\ArrayTransformer();
        $transformer->setConfig($transformerConfig);
        $dates = $transformer->transform($rule);

        if (count($dates) < 2) {
            return false;
        }

        return $dates;
    }

    public function getNextSendDate()
    {
        if ($this->start_date && !$this->last_sent_date) {
            $startDate = $this->getOriginal('start_date') . ' ' . $this->account->recurring_hour . ':00:00';
            return $this->account->getDateTime($startDate);
        }

        if (!$schedule = $this->getSchedule()) {
            return null;
        }

        if (count($schedule) < 2) {
            return null;
        }
        
        return $schedule[1]->getStart();
    }

    public function getPrettySchedule($min = 1, $max = 10)
    {
        if (!$schedule = $this->getSchedule($max)) {
            return null;
        }

        $dates = [];

        for ($i=$min; $i<min($max, count($schedule)); $i++) {
            $date = $schedule[$i];
            $date = $this->account->formatDate($date->getStart());
            $dates[] = $date;
        }

        return implode('<br/>', $dates);
    }

    private function getRecurrenceRule()
    {
        $rule = '';

        switch ($this->frequency_id) {
            case FREQUENCY_WEEKLY:
                $rule = 'FREQ=WEEKLY;';
                break;
            case FREQUENCY_TWO_WEEKS:
                $rule = 'FREQ=WEEKLY;INTERVAL=2;';
                break;
            case FREQUENCY_FOUR_WEEKS:
                $rule = 'FREQ=WEEKLY;INTERVAL=4;';
                break;
            case FREQUENCY_MONTHLY:
                $rule = 'FREQ=MONTHLY;';
                break;
            case FREQUENCY_THREE_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=3;';
                break;
            case FREQUENCY_SIX_MONTHS:
                $rule = 'FREQ=MONTHLY;INTERVAL=6;';
                break;
            case FREQUENCY_ANNUALLY:
                $rule = 'FREQ=YEARLY;';
                break;
        }

        if ($this->end_date) {
            $rule .= 'UNTIL=' . $this->end_date;
        }

        return $rule;
    }

    /*
    public function shouldSendToday()
    {
        if (!$nextSendDate = $this->getNextSendDate()) {
            return false;
        }
        
        return $this->account->getDateTime() >= $nextSendDate;
    }
    */

    public function shouldSendToday()
    {
        if (!$this->start_date || strtotime($this->start_date) > strtotime('now')) {
            return false;
        }

        if ($this->end_date && strtotime($this->end_date) < strtotime('now')) {
            return false;
        }

        $dayOfWeekToday = date('w');
        $dayOfWeekStart = date('w', strtotime($this->start_date));

        $dayOfMonthToday = date('j');
        $dayOfMonthStart = date('j', strtotime($this->start_date));

        if (!$this->last_sent_date) {
            return true;
        } else {
            $date1 = new DateTime($this->last_sent_date);
            $date2 = new DateTime();
            $diff = $date2->diff($date1);
            $daysSinceLastSent = $diff->format("%a");
            $monthsSinceLastSent = ($diff->format('%y') * 12) + $diff->format('%m');

            if ($daysSinceLastSent == 0) {
                return false;
            }
        }

        switch ($this->frequency_id) {
            case FREQUENCY_WEEKLY:
                return $daysSinceLastSent >= 7;
            case FREQUENCY_TWO_WEEKS:
                return $daysSinceLastSent >= 14;
            case FREQUENCY_FOUR_WEEKS:
                return $daysSinceLastSent >= 28;
            case FREQUENCY_MONTHLY:
                return $monthsSinceLastSent >= 1;
            case FREQUENCY_THREE_MONTHS:
                return $monthsSinceLastSent >= 3;
            case FREQUENCY_SIX_MONTHS:
                return $monthsSinceLastSent >= 6;
            case FREQUENCY_ANNUALLY:
                return $monthsSinceLastSent >= 12;
            default:
                return false;
        }

        return false;
    }

    public function getReminder()
    {
        for ($i=1; $i<=3; $i++) {
            $field = "enable_reminder{$i}";
            if (!$this->account->$field) {
                continue;
            }
            $field = "num_days_reminder{$i}";
            $date = date('Y-m-d', strtotime("- {$this->account->$field} days"));

            if ($this->due_date == $date) {
                return "reminder{$i}";
            }
        }

        return false;
    }

    public function getPDFString()
    {
        if (!env('PHANTOMJS_CLOUD_KEY')) {
            return false;
        }

        $invitation = $this->invitations[0];
        $link = $invitation->getLink();

        $curl = curl_init();
        $jsonEncodedData = json_encode([
            'targetUrl' => "{$link}?phantomjs=true",
            'requestType' => 'raw',
            'delayTime' => 1000,
        ]);

        $opts = [
            CURLOPT_URL => PHANTOMJS_CLOUD . env('PHANTOMJS_CLOUD_KEY'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $jsonEncodedData,
            CURLOPT_HTTPHEADER  => ['Content-Type: application/json', 'Content-Length: '.strlen($jsonEncodedData)],
        ];

        curl_setopt_array($curl, $opts);
        $encodedString = strip_tags(curl_exec($curl));
        curl_close($curl);

        return Utils::decodePDF($encodedString);
    }
}

Invoice::creating(function ($invoice) {
    if (!$invoice->is_recurring) {
        $invoice->account->incrementCounter($invoice);
    }
});

Invoice::created(function ($invoice) {
    Activity::createInvoice($invoice);
});

Invoice::updating(function ($invoice) {
    Activity::updateInvoice($invoice);
});

Invoice::deleting(function ($invoice) {
    Activity::archiveInvoice($invoice);
});

Invoice::restoring(function ($invoice) {
    Activity::restoreInvoice($invoice);
});