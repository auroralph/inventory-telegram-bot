<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Barang;
use App\Models\Riwayat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TelegramController extends Controller
{

    /* ================= WEBHOOK ================= */

    public function webhook(Request $request)
    {
        $u = $request->all();

        Log::info('WEBHOOK HIT', [
            'type' => array_key_exists('callback_query', $u) ? 'callback' : 'message'
        ]);

        if (isset($u['callback_query'])) {
            $cb = $u['callback_query'];
            Http::post($this->api('answerCallbackQuery'), [
                'callback_query_id' => $cb['id']
            ]);
            $this->handleCallback(
                $cb['message']['chat']['id'],
                $cb['data']
            );
            return response('ok');
        }

        $chatId = $u['message']['chat']['id'] ?? null;
        if (!$chatId) return response('no chat');

        if (!isset($u['message']['text'])) {
            return response('ok');
        }

        $text = trim($u['message']['text']);

        // reset state jika user kirim command baru
        if (str_starts_with($text, '/')) {
            cache()->forget("trx:$chatId");
            cache()->forget("add:$chatId");
        }

        if ($text === '/start') return $this->start($chatId);
        if ($text === '/menu') return $this->menu($chatId);
        if ($text === '/stok') return $this->stok($chatId, false);

        if ($text === '/editstok') {
            if (!$this->isAdmin($chatId)) return $this->send($chatId, "⛔ Admin only");
            return $this->stok($chatId, true);
        }

        if ($text === '/tambahbarang') {
            if (!$this->isAdmin($chatId)) return $this->send($chatId, "⛔ Admin only");
            return $this->tambahJenisButton($chatId);
        }

        if ($text === '/log') return $this->riwayatJenis($chatId);

        if ($text === '/cari')
            return $this->send($chatId, "Gunakan format:\n/cari JENIS_BARANG");

        if ($text === '/updatehari')
            return $this->riwayatHariIni($chatId);

        if (str_starts_with($text, '/cari '))
            return $this->cari($chatId, trim(substr($text, 6)));

        if ($text === '/laporan') return $this->laporanStok($chatId);

        if ($text === '/batal') {
            cache()->forget("trx:$chatId");
            cache()->forget("add:$chatId");
            return $this->menu($chatId, "❌ Dibatalkan");
        }

        if ($text === '/hapus') {
            if (!$this->isAdmin($chatId)) return $this->send($chatId, "⛔ Admin only");
            return $this->hapusJenis($chatId);
        }

        if (cache()->has("add:$chatId"))
            return $this->stepTambah($chatId, $text);

        if (cache()->has("trx:$chatId")) {
            if (!ctype_digit($text))
                return $this->send($chatId, "Masukkan angka");
            return $this->prosesTrx($chatId, (int)$text);
        }

        return $this->menu($chatId, "❓ Perintah Tidak dikenal");
    }

    /* ================= CONFIG ================= */

    private function api($m)
    {
        return "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/$m";
    }

    private function isAdmin($id)
    {
        return in_array((string)$id, array_map('trim', explode(',', env('TELEGRAM_ADMIN_ID'))));
    }

    /* ================= MENU ================= */

    private function start($c)
    {
        $msg = "🤖 INVENTORY BOT

Selamat datang di sistem stok asset.

📦 Cek Stok  -> /stok
🔎 Cari      -> /cari JENIS_BARANG
🛠️ Edit Stok -> /editstok

Ketik /menu kapan saja.";

        return $this->send($c, $msg);
    }

    private function menu($c, $t = "🏠 MENU")
    {
        return $this->send(
            $c,
            "$t\n\n/stok\n/cari JENIS_BARANG\n/editstok\n/tambahbarang\n/hapus\n/log\n/updatehari\n/laporan"
        );
    }

    /* ================= STOK ================= */

    private function stok($c, $edit)
    {

        $jenis = Barang::distinct()
            ->orderBy('jenis_barang')
            ->pluck('jenis_barang');

        if (!$jenis->count()) {
            return $this->send($c, "Belum ada data barang");
        }

        $kb = [];
        foreach ($jenis as $j) {
            $kb[] = [[
                'text' => $j,
                'callback_data' => ($edit ? 'jme:' : 'jm:') . $j
            ]];
        }

        return $this->send($c, "📦 Pilih Jenis Barang", $kb);
    }

    /* private function stokJenis($c,$j,$edit){

$items=Barang::where('jenis_barang',$j)->get();

$kb=[];
foreach($items as $b){
$kb[]=[[ 'text'=>"$b->nama_product | $b->stok",'callback_data'=>($edit?'pe:':'p:').$b->id ]];
}

return $this->send($c,"$j",$kb);
} */

    /* ================= DETAIL ================= */

    private function detail($c, $id)
    {
        $b = Barang::find($id);
        if (!$b) return $this->send($c, "Data tidak ada");

        $kb = [
            [
                ['text' => '➕', 'callback_data' => "m:$id"],
                ['text' => '➖', 'callback_data' => "k:$id"]
            ],
            [
                ['text' => '🔙', 'callback_data' => 'stok'],
                ['text' => '🏠', 'callback_data' => 'menu']
            ]
        ];

        return $this->send(
            $c,
            "📦 DETAIL BARANG\n\n" .
                "Jenis Barang: $b->jenis_barang\n" .
                "Merk: $b->merk\n\n" .
                "Nama Product: $b->nama_product\n" .
                "Stok: $b->stok $b->satuan\n" .
                "Lokasi: $b->lokasi",
            $kb
        );
    }

    /* ================= STOK MERK ================= */

    private function stokMerk($c, $jenis, $edit)
    {
        $jenis = trim($jenis);

        $merks = Barang::where('jenis_barang', $jenis)
            ->whereNotNull('merk')
            ->selectRaw('UPPER(merk) as merk')
            ->distinct()
            ->pluck('merk');

        if (!$merks->count()) {
            return $this->send($c, "⚠️ Jenis ini belum punya MERK.\nGunakan /tambahbarang dulu.");
        }

        $kb = [];
        foreach ($merks as $m) {
            $kb[] = [[
                'text' => $m,
                'callback_data' => ($edit ? 'merkedit:' : 'merk:') . "$jenis:$m"
            ]];
        }

        return $this->send($c, "Pilih Merk $jenis", $kb);
    }

    /* ================= CALLBACK ================= */

    private function handleCallback($c, $d)
    {
        if (!$d) return $this->menu($c);
        if ($d === 'menu') {
            cache()->forget("trx:$c");
            cache()->forget("add:$c");
            return $this->menu($c);
        }

        if ($d === 'stok') {
            cache()->forget("trx:$c");
            cache()->forget("add:$c");
            return $this->stok($c, false);
        }

        if ($d === 'editstok') {
            cache()->forget("trx:$c");
            cache()->forget("add:$c");
            return $this->stok($c, true);
        }

        if (str_starts_with($d, 'jm:'))
            return $this->stokMerk($c, substr($d, 3), false);

        if (str_starts_with($d, 'jme:'))
            return $this->stokMerk($c, substr($d, 4), true);

        if (str_starts_with($d, 'merk:')) {
            $parts = explode(':', $d, 3);
            $jenis = $parts[1] ?? '';
            $merk  = $parts[2] ?? '';

            $list = Barang::where('jenis_barang', $jenis)
                ->whereRaw('UPPER(merk)=?', [$merk])
                ->get();

            if (!$list->count()) {
                return $this->send($c, "⚠️ Produk belum ada");
            }

            $btn = [];
            foreach ($list as $b) {
                $btn[] = [['text' => $b->nama_product, 'callback_data' => "p:$b->id"]];
            }

            return $this->send($c, "Pilih Produk $jenis - $merk", $btn);
        }

        if (str_starts_with($d, 'merkedit:')) {
            $parts = explode(':', $d, 3);
            $jenis = $parts[1] ?? '';
            $merk  = $parts[2] ?? '';

            $list = Barang::where('jenis_barang', $jenis)
                ->whereRaw('UPPER(merk)=?', [$merk])
                ->get();

            if (!$list->count()) {
                return $this->send($c, "⚠️ Produk belum ada");
            }

            $btn = [];

            foreach ($list as $b) {
                $btn[] = [['text' => "$b->nama_product | $b->stok", 'callback_data' => "pe:$b->id"]];
            }

            return $this->send($c, "Pilih Produk $jenis - $merk", $btn);
        }

        /* ================= STOK FLOW ================= */

        if (str_starts_with($d, 'p:')) {
            $id = substr($d, 2);
            if (!ctype_digit($id)) return $this->menu($c);
            return $this->detail($c, $id);
        }

        if (str_starts_with($d, 'pe:')) {
            $id = substr($d, 3);
            if (!ctype_digit($id)) return $this->menu($c);
            return $this->detail($c, $id);
        }

        /* ================= TRANSAKSI ================= */

        if (str_starts_with($d, 'm:')) {
            $id = substr($d, 2);
            if (!ctype_digit($id)) return $this->menu($c);
            return $this->askJumlah($c, $id, 'MASUK');
        }

        if (str_starts_with($d, 'k:')) {
            $id = substr($d, 2);
            if (!ctype_digit($id)) return $this->menu($c);
            return $this->askJumlah($c, $id, 'KELUAR');
        }

        /* ================= HAPUS ================= */

        if (str_starts_with($d, 'delj:')) {
    if (!$this->isAdmin($c))
        return $this->send($c, "⛔ Admin only");
    return $this->hapusProdukList($c, substr($d, 5));
}

        if (str_starts_with($d, 'delp:')) {

            if (!$this->isAdmin($c))
                return $this->send($c, "⛔ Admin only");

            $id = substr($d, 5);
            if (!ctype_digit($id)) return $this->menu($c);

            $b = Barang::find($id);
            if ($b) $b->delete();

            return $this->send($c, "✅ Barang berhasil dihapus", [
                [['text' => '🔙 Jenis Barang', 'callback_data' => 'stok']],
                [['text' => '🏠 Menu', 'callback_data' => 'menu']]
            ]);
        }

        if ($d === 'laporan')
            return $this->laporanStok($c);

        /* ================= TAMBAH ================= */

        if (str_starts_with($d, 'addj:'))
            return $this->beginAdd($c, substr($d, 5));

        if (str_starts_with($d, 'rh:'))
            return $this->riwayatShow($c, substr($d, 3));

        if (str_starts_with($d, 'lapj:')) {
            return $this->laporanJenis($c, substr($d, 5));
        }

        return $this->menu($c);
    }

    /* ================= RIWAYAT HARI INI ================= */

    private function riwayatHariIni($c)
    {
        $today = now()->toDateString();
        $rows = Riwayat::whereDate('created_at', $today)->get();

        if (!$rows->count())
            return $this->send($c, "Belum ada transaksi hari ini");

        $msg =
            "<pre>" .
            "📋 Daily Update WH ASSET SEMARANG\n\n" .
            "Tanggal: " . now()->locale('id')->translatedFormat('d F Y') . "\n" .
            "Pukul: " . now()->format('H:i') . " WIB\n\n";

        $group = $rows->groupBy('jenis_barang');

        foreach ($group as $jenis => $items) {

            $msg .= "$jenis\n";

            $perProduk = $items->groupBy('nama_product');
            $lastIndex = $perProduk->count() - 1;
            $i = 0;

            foreach ($perProduk as $nama => $rowsProduk) {

                $last = $rowsProduk->last();
                $b = Barang::where('nama_product', $nama)->first();
                $satuan = $b?->satuan ?? '';

                $prefix = ($i == $lastIndex) ? "└" : "├";

                $msg .= "  $prefix $nama = {$last->stok_sesudah} $satuan\n";
                $i++;
            }

            $msg .= "\n";
        }

        $msg .= "</pre>";

        return $this->send($c, $msg, null, true);
    }

    /* ================= RIWAYAT ================= */

    private function riwayatShow($c, $jenis)
    {
        $rows = Riwayat::where('jenis_barang', $jenis)
            ->latest()
            ->limit(20)
            ->get();

        if (!$rows->count()) {
            return $this->send(
                $c,
                "📋 RIWAYAT $jenis\n\nBelum ada riwayat transaksi untuk jenis ini"
            );
        }

        $msg = "📋 RIWAYAT $jenis\n\n";

        foreach ($rows as $r) {
            $msg .=
                $r->created_at->format('d-m H:i') .
                " | {$r->nama_product} | {$r->tipe} {$r->jumlah}\n" .
                "({$r->stok_sebelum} → {$r->stok_sesudah})\n\n";
        }

        return $this->send($c, $msg);
    }

    /* ================= TRANSAKSI ================= */

    private function askJumlah($c, $id, $t)
    {
        cache()->put("trx:$c", ['id' => $id, 'tipe' => $t], 300);
        return $this->send($c, "Jumlah $t ?", [
            [['text' => '❌ Batal', 'callback_data' => 'menu']]
        ]);
    }

    private function prosesTrx($c, $j)
    {
        $trx = cache()->pull("trx:$c");
        if (!$trx) return $this->send($c, "⚠️ Transaksi tidak aktif");

        return DB::transaction(function () use ($trx, $j, $c) {

            $b = Barang::lockForUpdate()->find($trx['id']);
            if (!$b) return $this->send($c, "❌ Barang tidak ditemukan");

            if ($j <= 0 || $j > 10000)
                return $this->send($c, "❌ Jumlah harus 1–10000");

            if ($trx['tipe'] == 'KELUAR' && $b->stok < $j)
                return $this->send($c, "❌ Stok tidak cukup");

            $before = $b->stok;
            $b->stok += $trx['tipe'] == 'MASUK' ? $j : -$j;
            $b->save();

            Riwayat::create([
                'nama_product' => $b->nama_product,
                'jenis_barang' => $b->jenis_barang,
                'tipe' => $trx['tipe'],
                'jumlah' => $j,
                'stok_sebelum' => $before,
                'stok_sesudah' => $b->stok
            ]);

            return $this->detail($c, $b->id);
        });
    }

    /* ================= TAMBAH ================= */

    private function tambahJenisButton($c)
    {

        $jenis = Barang::distinct()
            ->orderBy('jenis_barang')
            ->pluck('jenis_barang');

        $kb = [];
        foreach ($jenis as $j) {
            $kb[] = [['text' => $j, 'callback_data' => "addj:$j"]];
        }

        $kb[] = [['text' => '➕ Jenis Baru', 'callback_data' => "addj:__new"]];

        return $this->send($c, "Pilih Jenis Barang", $kb);
    }

    private function beginAdd($c, $j)
    {

        if ($j === '__new') {
            cache()->put("add:$c", ['step' => 0], 600);
            return $this->send($c, "Masukkan JENIS BARANG baru:");
        }

        cache()->put("add:$c", ['step' => 1, 'jenis' => $j], 600);
        return $this->send($c, "Merk?", [
            [['text' => '❌ Batal', 'callback_data' => 'menu']]
        ]);
    }

    private function stepTambah($c, $text)
    {
        $s = cache()->get("add:$c");
        if (!$s) return;

        $text = trim($text);

        if ($text === '') {
            return $this->send($c, "❌ Tidak boleh kosong", $this->kbBatal());
        }

        if (strlen($text) > 100) {
            return $this->send($c, "❌ Maksimal 100 karakter", $this->kbBatal());
        }

        # STEP 0 — JENIS
        if ($s['step'] == 0) {

            $jenis = strtoupper($text);

            if (!preg_match('/^[A-Z0-9 \-]{3,50}$/', $jenis)) {
                return $this->send($c, "❌ Jenis tidak valid", $this->kbBatal());
            }

            if (Barang::where('jenis_barang', $jenis)->exists()) {
                return $this->send($c, "❌ Jenis sudah ada", $this->kbBatal());
            }

            $s['jenis'] = $jenis;
            $s['step'] = 1;
            cache()->put("add:$c", $s, 600);
            return $this->send($c, "Merk?", $this->kbBatal());
        }

        # STEP 1 — MERK
        if ($s['step'] == 1) {

            if (!preg_match('/^[A-Z0-9 \-]{2,50}$/', strtoupper($text))) {
                return $this->send($c, "❌ Merk tidak valid", $this->kbBatal());
            }

            $s['merk'] = strtoupper($text);
            $s['step'] = 2;
            cache()->put("add:$c", $s, 600);
            return $this->send($c, "Nama product?", $this->kbBatal());
        }

        # STEP 2 — PRODUK
        if ($s['step'] == 2) {

            if (!preg_match('/^[A-Z0-9 \-\[\]]{3,100}$/', strtoupper($text))) {
                return $this->send($c, "❌ Nama produk tidak valid", $this->kbBatal());
            }

            $s['nama'] = strtoupper($text);

            $s['step'] = 3;
            cache()->put("add:$c", $s, 600);
            return $this->send($c, "Satuan? (PCS/UNIT/DLL)", $this->kbBatal());
        }

        # STEP 3 — SATUAN
        if ($s['step'] == 3) {

            if (!preg_match('/^[a-zA-Z]{1,10}$/', trim($text))) {
                return $this->send($c, "❌ Satuan hanya huruf (maks 10 karakter)", $this->kbBatal());
            }

            $s['satuan'] = strtoupper(trim($text));
            $s['step'] = 4;
            cache()->put("add:$c", $s, 600);
            return $this->send($c, "Stok awal?", $this->kbBatal());
        }

        # STEP 4 — STOK
        if ($s['step'] == 4) {

            if (!ctype_digit($text))
                return $this->send($c, "Stok harus angka", $this->kbBatal());

            $stok = (int)$text;

            if ($stok < 0 || $stok > 100000)
                return $this->send($c, "❌ Stok maksimal 100000", $this->kbBatal());

            $s['stok'] = $stok;

            $s['step'] = 5;
            cache()->put("add:$c", $s, 600);
            return $this->send($c, "Lokasi gudang?", $this->kbBatal());
        }

        # STEP 5 — LOKASI + SIMPAN
        if ($s['step'] == 5) {

            if (!preg_match('/^[A-Z0-9 \-]{3,100}$/', strtoupper($text))) {
                return $this->send($c, "❌ Lokasi tidak valid", $this->kbBatal());
            }

            try {

                $barang = Barang::create([
                    'jenis_barang' => $s['jenis'],
                    'merk' => $s['merk'],
                    'nama_product' => $s['nama'],
                    'stok' => $s['stok'],
                    'satuan' => $s['satuan'],
                    'lokasi' => strtoupper($text)
                ]);

                cache()->forget("add:$c");

                return $this->detail($c, $barang->id);
            } catch (\Throwable $e) {

                Log::error('ADD BARANG ERROR', [
                    'err' => $e->getMessage(),
                    'data' => $s
                ]);

                return $this->send($c, "❌ ERROR SIMPAN:\n" . $e->getMessage());
            }
        }
        return $this->menu($c, "⚠️ Flow error, ulangi /tambahbarang");
    }

    private function kbBatal()
    {
        return [[['text' => '❌ Batal', 'callback_data' => 'menu']]];
    }

    /* ================= HAPUS ================= */

    private function hapusJenis($c)
    {

        $jenis = Barang::distinct()
            ->orderBy('jenis_barang')
            ->pluck('jenis_barang');
        $kb = [];
        foreach ($jenis as $j) {
            $kb[] = [['text' => $j, 'callback_data' => "delj:$j"]];
        }

        return $this->send($c, "Pilih Jenis Barang", $kb);
    }

    private function hapusProdukList($c, $j)
    {

        $items = Barang::where('jenis_barang', $j)->get();
        $kb = [];
        foreach ($items as $b) {
            $kb[] = [['text' => $b->nama_product, 'callback_data' => "delp:$b->id"]];
        }

        if (!$items->count()) {
            return $this->send($c, "Tidak ada produk");
        }

        return $this->send($c, "Hapus $j", $kb);
    }

    /* ================= RIWAYAT ================= */

    private function riwayatJenis($c)
    {

        $jenis = Barang::distinct()
            ->orderBy('jenis_barang')
            ->pluck('jenis_barang');
        $kb = [];
        foreach ($jenis as $j) {
            $kb[] = [['text' => $j, 'callback_data' => "rh:$j"]];
        }
        return $this->send($c, "Riwayat per jenis barang", $kb);
    }

    /* ================= CARI ================= */

    private function cari($c, $k)
    {

        $items = Barang::whereRaw('UPPER(nama_product) LIKE ?', ['%' . strtoupper($k) . '%'])->get();
        if (!$items->count()) return $this->send($c, "Tidak ada");

        $kb = [];
        foreach ($items as $b) {
            $kb[] = [['text' => $b->nama_product, 'callback_data' => "p:$b->id"]];
        }

        return $this->send($c, "Hasil pencarian: $k", $kb);
    }

    /* ================= SEND ================= */

    private function send($c, $t, $kb = null, $html = false)
    {

        $p = [
            'chat_id' => $c,
            'text' => $t
        ];

        if ($html) {
            $p['parse_mode'] = 'HTML';
        }

        if ($kb) {
            $p['reply_markup'] = json_encode([
                'inline_keyboard' => $kb
            ]);
        }

        $r = Http::post($this->api('sendMessage'), $p);

        if (!$r->ok()) {
            Log::error('TELEGRAM SEND FAIL', [
                'resp' => $r->body(),
                'payload' => $p
            ]);
        }

        return response('ok');
    }

    /* ================= LAPORAN STOK ================= */

    private function laporanStok($c)
    {

        $jenisList = Barang::distinct()
            ->orderBy('jenis_barang')
            ->pluck('jenis_barang');

        if (!$jenisList->count()) {
            return $this->send($c, "Belum ada data barang");
        }

        $msg =
            "<pre>" .
            "📋 Daily Update WH ASSET SEMARANG\n\n" .
            "Tanggal: " . now()->locale('id')->translatedFormat('d F Y') . "\n" .
            "Pukul: " . now()->format('H:i') . " WIB\n\n";

        foreach ($jenisList as $j) {

            $msg .= "$j\n";

            $items = Barang::where('jenis_barang', $j)
                ->orderBy('nama_product')
                ->get();
            $lastIndex = $items->count() - 1;
            $i = 0;

            foreach ($items as $b) {
                $prefix = ($i == $lastIndex) ? "└" : "├";
                $msg .= "  $prefix {$b->nama_product} = {$b->stok} {$b->satuan}\n";
                $i++;
            }

            $msg .= "\n";
        }

        $msg .= "</pre>";

        // tombol per jenis
        $kb = [];
        foreach ($jenisList as $j) {
            $kb[] = [[
                'text' => $j,
                'callback_data' => "lapj:$j"
            ]];
        }

        return $this->send($c, $msg, $kb, true);
    }

    private function laporanJenis($c, $jenis)
    {
        $items = Barang::where('jenis_barang', $jenis)
            ->orderBy('nama_product')
            ->get();

        if (!$items->count()) {
            return $this->send($c, "📋 LAPORAN $jenis\n\nBelum ada data");
        }

        $msg =
            "<pre>" .
            "📋 LAPORAN $jenis\n\n" .
            "Tanggal: " . now()->locale('id')->translatedFormat('d F Y') . "\n" .
            "Pukul: " . now()->format('H:i') . " WIB\n\n";

        $lastIndex = $items->count() - 1;
        $i = 0;

        foreach ($items as $b) {
            $prefix = ($i == $lastIndex) ? "└" : "├";
            $msg .= "  $prefix {$b->nama_product} = {$b->stok} {$b->satuan}\n";
            $i++;
        }

        $msg .= "</pre>";

        return $this->send($c, $msg, [
            [
                ['text' => '🔙 Semua Laporan', 'callback_data' => 'laporan'],
                ['text' => '🏠 Menu', 'callback_data' => 'menu']
            ]
        ], true);
    }
}
