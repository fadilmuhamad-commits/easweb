<?php

namespace App\Http\Controllers;

use App\Models\M_Config;
use App\Events\E_ShowWebsocket;
use App\Models\M_Counter_Category;
use App\Models\M_Customer;
use App\Models\M_Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class C_Pengunjung extends Controller
{
  public function dataAntrian(Request $request)
  {
    $user = auth()->user();

    $data['counters'] = DB::table('counters')->get();

    if ($user->Counter && !$user->Role->hasPermission('view_all_counter')) {
      $data['categories'] = M_Counter_Category::whereHas('Counters', function ($query) use ($user) {
        $query->where('counters.id', $user->Counter->id);
      })->get();
    } else {
      $data['categories'] = M_Counter_Category::get();
    }

    $query = M_Ticket::query();

    // ADMIN
    $query
      ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.id')
      ->leftJoin('counter_categories', 'tickets.counter_category_id', '=', 'counter_categories.id')
      ->leftJoin('ticket_categories', 'tickets.ticket_category_id', '=', 'ticket_categories.id')
      ->leftJoin('relation_counter_categories', 'relation_counter_categories.counter_category_id', '=', 'counter_categories.id')
      ->leftJoin('counters', 'relation_counter_categories.counter_id', '=', 'counters.id')
      ->leftJoin('groups', 'counters.group_id', '=', 'groups.id')
      ->select('tickets.*', 'customers.name as name', 'customers.email as email', 'customers.address as address', 'customers.phone_number as phone_number', 'customers.birth_place as birth_place', 'customers.birth_date as birth_date')
      ->selectRaw('GROUP_CONCAT(counters.name ORDER BY counters.id SEPARATOR ", ") as counters')
      ->selectRaw('GROUP_CONCAT(DISTINCT groups.name ORDER BY groups.id SEPARATOR ", ") as group_names')
      ->whereNotNull('tickets.queue_number')
      ->whereNotNull('tickets.counter_category_id')
      ->where(function ($query) {
        $query->where('tickets.status', '=', 2)
          ->orWhere('tickets.status', '=', 3);
      })
      ->groupBy(
        'tickets.id',
        'tickets.booking_code',
        'tickets.queue_number',
        'tickets.customer_id',
        'tickets.counter_category_id',
        'tickets.ticket_category_id',
        'tickets.position',
        'tickets.status',
        'tickets.counter_id',
        'tickets.duration',
        'tickets.note',
        'tickets.counter_category_code',
        'tickets.ticket_category_name',
        'tickets.counter_name',
        'tickets.group_name',
        'tickets.created_at',
        'tickets.updated_at',
        'customers.name',
        'customers.email',
        'customers.address',
        'customers.phone_number',
        'customers.birth_place',
        'customers.birth_date',
      );

    if ($user->Counter && !$user->Role->hasPermission('view_all_counter')) {
      // ADMIN
      $query
        ->where('counters.id', '=', $user->counter_id);
    }

    // FILTER
    $data['selectedCounter'] = $request->input('selected_counter');
    $data['selectedCounterCategory'] = $request->input('selected_category');
    $data['selectedDate'] = $request->input('selected_date');

    $selectedCounter = $data['selectedCounter'];
    $selectedCounterCategory = $data['selectedCounterCategory'];
    $selectedDate = $data['selectedDate'];

    if (!empty($selectedDate)) {
      $monthIDN = [
        'Mei' => 'May',
        'Agu' => 'Aug',
        'Okt' => 'Oct',
        'Des' => 'Dec',
      ];

      $selectedDate = strtr($selectedDate, $monthIDN);
      $carbonDate = Carbon::createFromFormat('d M Y', $selectedDate);

      $query
        ->whereDay('tickets.created_at', '=', $carbonDate->format('d'))
        ->whereMonth('tickets.created_at', '=', $carbonDate->format('m'))
        ->whereYear('tickets.created_at', '=', $carbonDate->format('Y'));
    }

    if (!empty($selectedCounter)) {
      $query
        ->where('counters.id', '=', $selectedCounter);
    }

    if (!empty($selectedCounterCategory)) {
      $query
        ->where('counter_categories.id', '=', $selectedCounterCategory);
    }
    // END FILTER

    // SEARCH
    $data['search'] = $request->input('search');
    $data['searchBy'] = $request->input('search_by');

    $search = $data['search'];
    $searchBy = $data['searchBy'];

    if (!empty($search) && empty($searchBy)) {
      $query->where(function ($query) use ($search) {
        $query
          ->where('customers.name', 'like', '%' . $search . '%')
          ->orWhere('customers.email', 'like', '%' . $search . '%')
          ->orWhere('customers.phone_number', 'like', '%' . $search . '%')
          ->orWhere('customers.address', 'like', '%' . $search . '%')
          ->orWhere('booking_code', 'like', '%' . $search . '%')
          ->orWhere('counter_categories.code', 'like', '%' . $search . '%')
          ->orWhere('ticket_categories.name', 'like', '%' . $search . '%')
          ->orWhere('queue_number', 'like', '%' . $search . '%')
          ->orWhereRaw("CONCAT(counter_categories.code, '-', queue_number) LIKE ?", ['%' . $search . '%']);
      });
    } else if (!empty($search) && !empty($searchBy)) {
      $query
        ->where('customers.' . $searchBy, 'like', '%' . $search . '%');
    }
    // END SEARCH

    // SORT
    $data['sort'] = $request->input('sort');
    $data['order'] = $request->input('order');

    $sort = $data['sort'];
    $order = $data['order'];

    if (!empty($sort) && !empty($order)) {
      $query->orderBy($sort, $order);
    } else {
      $query
        ->orderBy('status', 'desc')
        ->orderBy('position', 'asc');
    }
    // END SORT

    // PER PAGE
    $data['perPage'] = $request->input('perPage');

    if (empty($data['perPage']) || !is_numeric($data['perPage'])) {
      $data['perPage'] = 10;
    }
    // END PER PAGE

    $data['tickets'] = $query->paginate($data['perPage']);
    $data['length'] = $data['tickets']->total();
    $config = M_Config::first();
    $data['config'] = $config;
    return view('pages.admin.antrian.data-antrian', $data);
  }

  public function riwayatKunjungan(Request $request)
  {
    $user = auth()->user();

    $data['counters'] = DB::table('counters')->get();

    if ($user->Counter && !$user->Role->hasPermission('view_all_counter')) {
      $data['categories'] = M_Counter_Category::whereHas('Counters', function ($query) use ($user) {
        $query->where('counters.id', $user->Counter->id);
      })->get();
    } else {
      $data['categories'] = M_Counter_Category::get();
    }

    $query = M_Ticket::query();

    // ADMIN
    $query
      ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.id')
      ->leftJoin('counter_categories', 'tickets.counter_category_id', '=', 'counter_categories.id')
      ->leftJoin('counters', 'tickets.counter_id', '=', 'counters.id')
      ->select('tickets.*', 'customers.name as name', 'customers.email as email', 'customers.address as address', 'customers.phone_number as phone_number', 'customers.birth_place as birth_place', 'customers.birth_date as birth_date')
      ->where('tickets.status', '=', 1);

    if ($user->Counter && !$user->Role->hasPermission('view_all_counter')) {
      // ADMIN
      $query
        ->where('counters.id', '=', $user->counter_id);
    }

    // FILTER
    $data['selectedCounter'] = $request->input('selected_counter');
    $data['selectedCounterCategory'] = $request->input('selected_category');
    $data['selectedDate'] = $request->input('selected_date');

    $selectedCounter = $data['selectedCounter'];
    $selectedCounterCategory = $data['selectedCounterCategory'];
    $selectedDate = $data['selectedDate'];

    if (!empty($selectedDate)) {
      $monthIDN = [
        'Mei' => 'May',
        'Agu' => 'Aug',
        'Okt' => 'Oct',
        'Des' => 'Dec',
      ];

      $selectedDate = strtr($selectedDate, $monthIDN);
      $carbonDate = Carbon::createFromFormat('d F Y', $selectedDate);

      $query
        ->whereDay('tickets.updated_at', '=', $carbonDate->format('d'))
        ->whereMonth('tickets.updated_at', '=', $carbonDate->format('m'))
        ->whereYear('tickets.updated_at', '=', $carbonDate->format('Y'));
    }

    if (!empty($selectedCounter)) {
      $query
        ->where('counters.id', '=', $selectedCounter);
    }

    if (!empty($selectedCounterCategory)) {
      $query
        ->where('counter_categories.id', '=', $selectedCounterCategory);
    }
    // END FILTER

    // SEARCH
    $data['search'] = $request->input('search');
    $data['searchBy'] = $request->input('search_by');

    $search = $data['search'];
    $searchBy = $data['searchBy'];

    if (!empty($search) && empty($searchBy)) {
      $query->where(function ($query) use ($search) {
        $query
          ->where('customers.name', 'like', '%' . $search . '%')
          ->orWhere('customers.email', 'like', '%' . $search . '%')
          ->orWhere('customers.phone_number', 'like', '%' . $search . '%')
          ->orWhere('customers.address', 'like', '%' . $search . '%')
          ->orWhere('booking_code', 'like', '%' . $search . '%')
          ->orWhere('counter_category_code', 'like', '%' . $search . '%')
          ->orWhere('ticket_category_name', 'like', '%' . $search . '%')
          ->orWhere('counter_name', 'like', '%' . $search . '%')
          ->orWhere('group_name', 'like', '%' . $search . '%')
          ->orWhere('queue_number', 'like', '%' . $search . '%')
          ->orWhereRaw("CONCAT(counter_category_code, '-', queue_number) LIKE ?", ['%' . $search . '%']);
      });
    } else if (!empty($search) && !empty($searchBy)) {
      $query
        ->where('customers.' . $searchBy, 'like', '%' . $search . '%');
    }
    // END SEARCH

    // SORT
    $data['sort'] = $request->input('sort');
    $data['order'] = $request->input('order');

    $sort = $data['sort'];
    $order = $data['order'];

    if (!empty($sort) && !empty($order)) {
      $query->orderBy($sort, $order);
    } else {
      $query->orderBy('updated_at', 'desc');
    }
    // END SORT

    // PER PAGE
    $data['perPage'] = $request->input('perPage');

    if (empty($data['perPage']) || !is_numeric($data['perPage'])) {
      $data['perPage'] = 10;
    }
    // END PER PAGE

    $data['tickets'] = $query->paginate($data['perPage']);
    $data['length'] = $data['tickets']->total();
    $config = M_Config::first();
    $data['config'] = $config;
    return view('pages.admin.riwayat-kunjungan', $data);
  }

  public function dataAntrianEdit(M_Ticket $ticket)
  {
    $data['customer'] = DB::table('tickets')
      ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.id')
      ->select('tickets.*', 'customers.name as name', 'customers.email as email', 'customers.address as address', 'customers.phone_number as phone_number', 'customers.birth_place as birth_place', 'customers.birth_date as birth_date')
      ->where('tickets.id', '=', $ticket->id)
      ->first();

    $config = M_Config::first();
    $data['config'] = $config;
    return view('pages.admin.antrian.data-antrian-edit', $data);
  }

  public function destroy(M_Ticket $ticket)
  {
    $ticket->delete();

    // try {
    //   Broadcast(new E_ShowWebsocket);
    // } catch (\Exception $e) {
    //   Log::error('Pusher broadcast error: ' . $e->getMessage());
    // }

    return redirect()->back()->with('success', 'Antrian deleted successfully');
  }

  public function destroySelected(Request $request)
  {
    $selectedRows = json_decode($request->input('selectedRows'));

    foreach ($selectedRows as $rowId) {
      $ticket = M_Ticket::findOrFail($rowId);

      $ticket->delete();
    }

    // try {
    //   Broadcast(new E_ShowWebsocket);
    // } catch (\Exception $e) {
    //   Log::error('Pusher broadcast error: ' . $e->getMessage());
    // }

    return redirect()->back()->with('success', count($selectedRows) . ' row(s) deleted successfully');
  }


  public function noteStore(Request $request, M_Ticket $ticket)
  {
    $ticket->update([
      'note' => $request->input('note-input')
    ]);

    return redirect()->back()->with('success', 'Catatan customer updated successfully');
  }

  public function edit(Request $request, M_Ticket $ticket)
  {
    $request->validate([
      'nama-pengunjung' => 'required',
      'email-pengunjung' => 'required|email',
      'telp-pengunjung' => 'required',
      'alamat-pengunjung' => 'required',
      'tempat-lahir-pengunjung' => 'required',
      'tanggal-lahir-pengunjung' => 'required',
    ], [
      'nama-pengunjung.required' => 'Username wajib diisi',
      'email-pengunjung.required' => 'Email wajib diisi',
      'telp-pengunjung.required' => 'No. Telp wajib diisi',
      'alamat-pengunjung.required' => 'Address wajib diisi',
      'tempat-lahir-pengunjung.required' => 'Tempat Lahir wajib diisi',
      'tanggal-lahir-pengunjung.required' => 'Tanggal Lahir wajib diisi',
    ]);

    $customer = $ticket->Customer;
    $existingCustomer = M_Customer::where('email', $request->input('email-pengunjung'))
      ->orWhere('phone_number', $request->input('telp-pengunjung'))
      ->first();

    if ($existingCustomer) {
      $ticket->update([
        'customer_id' => $existingCustomer->id
      ]);
      $existingCustomer->update([
        'name' => $request->input('nama-pengunjung'),
        'email' => $request->input('email-pengunjung'),
        'phone_number' => $request->input('telp-pengunjung'),
        'address' => $request->input('alamat-pengunjung'),
        'birth_place' => $request->input('tempat-lahir-pengunjung'),
        'birth_date' => $request->input('tanggal-lahir-pengunjung'),
      ]);
    } else if ($ticket->customer_id && !$existingCustomer) {
      do {
        $randomNoInduk = random_int(100000000, 999999999);
      } while (M_Customer::where('registration_code', $randomNoInduk)->exists());
      $customer = M_Customer::create([
        'registration_code' => $randomNoInduk,
        'name' => $request->input('nama-pengunjung'),
        'email' => $request->input('email-pengunjung'),
        'phone_number' => $request->input('telp-pengunjung'),
        'address' => $request->input('alamat-pengunjung'),
        'birth_place' => $request->input('tempat-lahir-pengunjung'),
        'birth_date' => $request->input('tanggal-lahir-pengunjung'),
      ]);
      $ticket->update([
        'customer_id' => $customer->id
      ]);
    } else {
      do {
        $randomNoInduk = random_int(100000000, 999999999);
      } while (M_Customer::where('registration_code', $randomNoInduk)->exists());
      $customer = M_Customer::create([
        'registration_code' => $randomNoInduk,
        'name' => $request->input('nama-pengunjung'),
        'email' => $request->input('email-pengunjung'),
        'phone_number' => $request->input('telp-pengunjung'),
        'address' => $request->input('alamat-pengunjung'),
        'birth_place' => $request->input('tempat-lahir-pengunjung'),
        'birth_date' => $request->input('tanggal-lahir-pengunjung'),
      ]);

      $ticket->update([
        'customer_id' => $customer->id,
      ]);
    }

    return redirect()->route('antrian')->with('success', 'Antrian updated successfully');
  }

  public function dataPengunjung(Request $request)
  {
    $query = M_Customer::query();
    $query
      ->leftJoin('tickets', 'tickets.customer_id', '=', 'customers.id')
      ->select('customers.*')
      ->selectRaw('COUNT(CASE WHEN tickets.status = 1 THEN 1 ELSE NULL END) as tickets_total')
      ->groupBy(
        'customers.id',
        'customers.registration_code',
        'customers.name',
        'customers.email',
        'customers.birth_place',
        'customers.birth_date',
        'customers.phone_number',
        'customers.address',
        'customers.type',
        'customers.created_at',
        'customers.updated_at'
      );

    // SEARCH
    $data['search'] = $request->input('search');
    $data['searchBy'] = $request->input('search_by');

    $search = $data['search'];
    $searchBy = $data['searchBy'];

    if (!empty($search) && empty($searchBy)) {
      $query->where(function ($query) use ($search) {
        $query
          ->where('customers.name', 'like', '%' . $search . '%')
          ->orWhere('customers.registration_code', 'like', '%' . $search . '%')
          ->orWhere('customers.email', 'like', '%' . $search . '%')
          ->orWhere('customers.phone_number', 'like', '%' . $search . '%')
          ->orWhere('customers.address', 'like', '%' . $search . '%')
          ->orWhere('customers.birth_place', 'like', '%' . $search . '%')
          ->orWhere('customers.birth_date', 'like', '%' . $search . '%')
          ->orWhere('customers.type', 'like', '%' . $search . '%');
      });
    } else if (!empty($search) && !empty($searchBy)) {
      $query
        ->where('customers.' . $searchBy, 'like', '%' . $search . '%');
    }
    // END SEARCH

    // SORT
    $data['sort'] = $request->input('sort');
    $data['order'] = $request->input('order');

    $sort = $data['sort'];
    $order = $data['order'];

    if (!empty($sort) && !empty($order)) {
      $query->orderBy($sort, $order);
    } else {
      $query->orderBy('customers.created_at', 'desc');
    }
    // END SORT

    // CUSTOMERS TYPE
    $data['selectedType'] = $request->input('customer_type');

    if (!empty($data['selectedType'])) {
      $query->where('customers.type', $data['selectedType']);
    }
    // END CUSTOMERS TYPE

    // PER PAGE
    $data['perPage'] = $request->input('perPage');

    if (empty($data['perPage']) || !is_numeric($data['perPage'])) {
      $data['perPage'] = 10;
    }
    // END PER PAGE

    $data['customers'] = $query->paginate($data['perPage']);
    $data['length'] = $data['customers']->total();
    $config = M_Config::first();
    $data['config'] = $config;
    return view('pages.admin.data-pengunjung', $data);
  }

  public function dataBooking(Request $request)
  {
    $user = auth()->user();

    $data['counters'] = DB::table('counters')->get();

    if ($user->Counter && !$user->Role->hasPermission('view_all_counter')) {
      $data['categories'] = M_Counter_Category::whereHas('Counters', function ($query) use ($user) {
        $query->where('counters.id', $user->Counter->id);
      })->get();
    } else {
      $data['categories'] = M_Counter_Category::get();
    }

    $query = M_Ticket::query();

    // ADMIN
    $query
      ->leftJoin('customers', 'tickets.customer_id', '=', 'customers.id')
      ->leftJoin('counter_categories', 'tickets.counter_category_id', '=', 'counter_categories.id')
      ->leftJoin('ticket_categories', 'tickets.ticket_category_id', '=', 'ticket_categories.id')
      ->leftJoin('relation_counter_categories', 'relation_counter_categories.counter_category_id', '=', 'counter_categories.id')
      ->leftJoin('counters', 'relation_counter_categories.counter_id', '=', 'counters.id')
      ->select(
        'tickets.*',
        'customers.name as name',
        'customers.registration_code as registration_code',
        'customers.email as email',
        'customers.address as address',
        'customers.phone_number as phone_number',
        'customers.birth_place as birth_place',
        'customers.birth_date as birth_date',
        'customers.type as type',

      )
      ->selectRaw('GROUP_CONCAT(counters.name ORDER BY counters.id SEPARATOR ", ") as counters')
      ->whereNotNull('tickets.queue_number')
      ->whereNotNull('tickets.booking_code')
      ->whereNotNull('tickets.counter_category_id')
      ->where('tickets.status', '=', 4)
      ->groupBy(
        'tickets.id',
        'tickets.booking_code',
        'tickets.queue_number',
        'tickets.customer_id',
        'tickets.counter_category_id',
        'tickets.ticket_category_id',
        'tickets.position',
        'tickets.status',
        'tickets.counter_id',
        'tickets.duration',
        'tickets.note',
        'tickets.counter_category_code',
        'tickets.ticket_category_name',
        'tickets.counter_name',
        'tickets.group_name',
        'tickets.created_at',
        'tickets.updated_at',
        'customers.name',
        'customers.registration_code',
        'customers.email',
        'customers.address',
        'customers.phone_number',
        'customers.birth_place',
        'customers.birth_date',
        'customers.type',
        'counter_categories.code'
      );

    if ($user->Counter && !$user->Role->hasPermission('view_all_counter')) {
      // ADMIN
      $query
        ->where('counters.id', '=', $user->counter_id);
    }

    // FILTER
    $data['selectedCounterCategory'] = $request->input('selected_category');
    $data['selectedDate'] = $request->input('selected_date');

    $selectedCounterCategory = $data['selectedCounterCategory'];
    $selectedDate = $data['selectedDate'];

    if (!empty($selectedDate)) {
      $monthIDN = [
        'Mei' => 'May',
        'Agu' => 'Aug',
        'Okt' => 'Oct',
        'Des' => 'Dec',
      ];

      $selectedDate = strtr($selectedDate, $monthIDN);
      $carbonDate = Carbon::createFromFormat('d M Y', $selectedDate);

      $query
        ->whereDay('tickets.created_at', '=', $carbonDate->format('d'))
        ->whereMonth('tickets.created_at', '=', $carbonDate->format('m'))
        ->whereYear('tickets.created_at', '=', $carbonDate->format('Y'));
    }

    if (!empty($selectedCounterCategory)) {
      $query
        ->where('counter_categories.id', '=', $selectedCounterCategory);
    }
    // END FILTER

    // SEARCH
    $data['search'] = $request->input('search');
    $data['searchBy'] = $request->input('search_by');

    $search = $data['search'];
    $searchBy = $data['searchBy'];

    if (!empty($search) && empty($searchBy)) {
      $query->where(function ($query) use ($search) {
        $query
          ->where('customers.name', 'like', '%' . $search . '%')
          ->orWhere('customers.email', 'like', '%' . $search . '%')
          ->orWhere('customers.phone_number', 'like', '%' . $search . '%')
          ->orWhere('customers.address', 'like', '%' . $search . '%')
          ->orWhere('booking_code', 'like', '%' . $search . '%')
          ->orWhere('counter_categories.code', 'like', '%' . $search . '%')
          ->orWhere('ticket_categories.name', 'like', '%' . $search . '%')
          ->orWhere('queue_number', 'like', '%' . $search . '%')
          ->orWhereRaw("CONCAT(counter_categories.code, '-', queue_number) LIKE ?", ['%' . $search . '%']);
      });
    } else if (!empty($search) && !empty($searchBy)) {
      $query
        ->where('customers.' . $searchBy, 'like', '%' . $search . '%');
    }
    // END SEARCH

    // SORT
    $data['sort'] = $request->input('sort');
    $data['order'] = $request->input('order');

    $sort = $data['sort'];
    $order = $data['order'];

    if (!empty($sort) && !empty($order)) {
      $query->orderBy($sort, $order);
    } else {
      $query
        ->orderBy('tickets.created_at', 'desc');
    }
    // END SORT

    // CUSTOMERS TYPE
    $data['selectedType'] = $request->input('customer_type');

    if (!empty($data['selectedType'])) {
      $query->where('customers.type', $data['selectedType']);
    }
    // END CUSTOMERS TYPE

    // PER PAGE
    $data['perPage'] = $request->input('perPage');

    if (empty($data['perPage']) || !is_numeric($data['perPage'])) {
      $data['perPage'] = 10;
    }
    // END PER PAGE

    $data['tickets'] = $query->paginate($data['perPage']);
    $data['length'] = $data['tickets']->total();
    $config = M_Config::first();
    $data['config'] = $config;
    return view('pages.admin.data-booking', $data);
  }

  public function destroyBooking(M_Ticket $ticket)
  {
    $ticket->delete();

    return redirect()->back()->with('success', 'Tickets booking deleted successfully');
  }

  public function destroyBookingSelected(Request $request)
  {
    $selectedRows = json_decode($request->input('selectedRows'));

    foreach ($selectedRows as $rowId) {
      $ticket = M_Ticket::findOrFail($rowId);

      $ticket->delete();
    }

    return redirect()->back()->with('success', count($selectedRows) . ' row(s) deleted successfully');
  }
}
