<x-layouts.app title="Reports">
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="font-display text-3xl font-bold">Reports</h1>
        <p class="mt-2 max-w-prose text-ink/70">
            Bookings per month and how full the resort was. Counts only.
        </p>

        <form method="GET" action="{{ route('admin.reports') }}" class="mt-6 flex items-end gap-3">
            <div>
                <label for="year" class="block text-sm font-semibold">Year</label>
                <select id="year" name="year"
                        class="mt-1.5 block rounded-lg border border-line bg-white px-3.5 py-2.5 text-base focus:border-pool focus:outline-none focus:ring-2 focus:ring-sky/40">
                    @foreach ($years as $option)
                        <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-pool px-5 py-2.5 text-base font-semibold text-white hover:bg-ink">Show</button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-xl border border-line bg-white">
            <table class="w-full min-w-[34rem] text-left text-sm">
                <caption class="sr-only">Bookings and occupancy per month, {{ $year }}</caption>
                <thead class="border-b border-line bg-mist text-ink/70">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-semibold">Month</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Bookings</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Walk-ins</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Dates booked</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Occupancy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row" class="px-4 py-3 font-semibold">{{ $row['label'] }}</th>
                            <td class="px-4 py-3 text-right">{{ $row['bookings'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['walk_ins'] }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['dates_booked'] }} of {{ $row['days'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-2.5 w-24 overflow-hidden rounded-full bg-mist sm:w-40" aria-hidden="true">
                                        <div class="h-full rounded-full bg-pool" style="width: {{ $row['occupancy'] }}%"></div>
                                    </div>
                                    <span class="w-10 text-right font-semibold">{{ $row['occupancy'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-line bg-mist font-semibold">
                    <tr>
                        <th scope="row" class="px-4 py-3">Whole year</th>
                        <td class="px-4 py-3 text-right">{{ $totals['bookings'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $totals['walk_ins'] }}</td>
                        <td class="px-4 py-3 text-right">{{ $totals['dates_booked'] }} of {{ $totals['days'] }}</td>
                        <td class="px-4 py-3">{{ $totals['occupancy'] }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="mt-4 max-w-prose text-sm text-ink/60">
            Only Confirmed reservations are counted, in the month of the reserved date. Reservations that are still held, waiting for payment, flagged, expired or cancelled are not counted. The resort takes one group per date, so occupancy is the dates booked out of the days in the month.
        </p>
    </div>
</x-layouts.app>
