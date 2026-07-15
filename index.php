<?php
include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// Ambil daftar menu dari database
$result = $conn->query("SELECT * FROM menu ORDER BY kategori, nama_item");
$menus = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $menus[] = $row;
    }
}

$featuredMenus = [];
$menuNameLookup = [];
foreach ($menus as $menu) {
    $menuNameLookup[strtolower(trim($menu['nama_item']))] = $menu;
}

$popularity = [];
$salesStmt = $conn->prepare("SELECT detail_menu FROM transaksi");
$salesStmt->execute();
$salesResult = $salesStmt->get_result();

if ($salesResult && $salesResult->num_rows > 0) {
    while ($row = $salesResult->fetch_assoc()) {
        $detailMenu = trim($row['detail_menu']);
        if ($detailMenu === '') {
            continue;
        }

        $segments = preg_split('/\s*,\s*/', $detailMenu);
        foreach ($segments as $segment) {
            $cleanSegment = trim($segment);
            $cleanSegment = preg_replace('/\s*\[[^\]]+\]\s*$/', '', $cleanSegment);
            $cleanSegment = preg_replace('/\s*\(\d+x\)\s*$/', '', $cleanSegment);
            $cleanSegment = trim($cleanSegment);
            $lookupKey = strtolower($cleanSegment);

            if (isset($menuNameLookup[$lookupKey])) {
                $menuId = (int) $menuNameLookup[$lookupKey]['id'];
                $popularity[$menuId] = ($popularity[$menuId] ?? 0) + 1;
            }
        }
    }
}
$salesStmt->close();

arsort($popularity);
$featuredIds = array_keys($popularity);
if (count($featuredIds) < 5) {
    foreach ($menus as $menu) {
        $menuId = (int) $menu['id'];
        if (!in_array($menuId, $featuredIds, true)) {
            $featuredIds[] = $menuId;
        }
        if (count($featuredIds) >= 5) {
            break;
        }
    }
}

$featuredIds = array_slice($featuredIds, 0, 5);
foreach ($featuredIds as $featuredId) {
    foreach ($menus as $menu) {
        if ((int) $menu['id'] === (int) $featuredId) {
            $featuredMenus[] = $menu;
            break;
        }
    }
}

// Ambil tanggal laporan untuk tab laporan
$tanggal_dipilih = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

$stmt = $conn->prepare("SELECT * FROM transaksi WHERE DATE(tanggal) = ? ORDER BY tanggal DESC");
$stmt->bind_param("s", $tanggal_dipilih);
$stmt->execute();
$hasil_transaksi = $stmt->get_result();

$transaksi_list = [];
$grand_total = 0;

if ($hasil_transaksi && $hasil_transaksi->num_rows > 0) {
    while ($row = $hasil_transaksi->fetch_assoc()) {
        $transaksi_list[] = $row;
        $grand_total += (int) $row['total_penjualan'];
    }
}
$stmt->close();

$pengeluaranStmt = $conn->prepare("SELECT * FROM pengeluaran WHERE DATE(tanggal) = ? ORDER BY tanggal DESC");
$pengeluaranStmt->bind_param("s", $tanggal_dipilih);
$pengeluaranStmt->execute();
$pengeluaranResult = $pengeluaranStmt->get_result();

$pengeluaran_list = [];
$pengeluaran_total = 0;

if ($pengeluaranResult && $pengeluaranResult->num_rows > 0) {
    while ($row = $pengeluaranResult->fetch_assoc()) {
        $pengeluaran_list[] = $row;
        $pengeluaran_total += (int) $row['nominal'];
    }
}
$pengeluaranStmt->close();

$laporan_harian = [];
foreach ($transaksi_list as $trx) {
    $laporan_harian[] = [
        'jenis' => 'penjualan',
        'waktu' => $trx['tanggal'],
        'keterangan' => !empty($trx['detail_menu']) ? $trx['detail_menu'] : 'Tidak ada detail (transaksi lama)',
        'nominal' => (int) $trx['total_penjualan'],
        'badge' => 'Penjualan',
        'badge_class' => 'bg-stone-100 text-stone-700',
        'nominal_class' => 'text-stone-800',
    ];
}

foreach ($pengeluaran_list as $pengeluaran) {
    $laporan_harian[] = [
        'jenis' => 'pengeluaran',
        'waktu' => $pengeluaran['tanggal'],
        'keterangan' => $pengeluaran['catatan'],
        'nominal' => (int) $pengeluaran['nominal'],
        'badge' => 'Pengeluaran',
        'badge_class' => 'bg-red-100 text-red-700',
        'nominal_class' => 'text-red-600',
    ];
}

usort($laporan_harian, function ($a, $b) {
    return strtotime($b['waktu']) <=> strtotime($a['waktu']);
});

$bulan_ini = date('Y-m');
$monthlySummaryStmt = $conn->prepare("SELECT COALESCE(SUM(total_penjualan), 0) AS monthly_total FROM transaksi WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?");
$monthlySummaryStmt->bind_param("s", $bulan_ini);
$monthlySummaryStmt->execute();
$monthlySummaryResult = $monthlySummaryStmt->get_result();
$monthly_total = 0;

if ($monthlySummaryResult && $monthlySummaryResult->num_rows > 0) {
    $monthlyRow = $monthlySummaryResult->fetch_assoc();
    $monthly_total = (int) $monthlyRow['monthly_total'];
}
$monthlySummaryStmt->close();

$monthlyPengeluaranStmt = $conn->prepare("SELECT COALESCE(SUM(nominal), 0) AS monthly_pengeluaran_total FROM pengeluaran WHERE DATE_FORMAT(tanggal, '%Y-%m') = ?");
$monthlyPengeluaranStmt->bind_param("s", $bulan_ini);
$monthlyPengeluaranStmt->execute();
$monthlyPengeluaranResult = $monthlyPengeluaranStmt->get_result();
$monthly_pengeluaran_total = 0;

if ($monthlyPengeluaranResult && $monthlyPengeluaranResult->num_rows > 0) {
    $monthlyPengeluaranRow = $monthlyPengeluaranResult->fetch_assoc();
    $monthly_pengeluaran_total = (int) $monthlyPengeluaranRow['monthly_pengeluaran_total'];
}
$monthlyPengeluaranStmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kasir Angkringan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html { scroll-behavior: smooth; }
        body { background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%); }
        .tab-btn.active { background: #1c1917; color: white; box-shadow: 0 10px 30px rgba(28, 25, 23, 0.18); }
        .tab-panel.hidden { display: none; }
    </style>
</head>
<body class="font-sans min-h-screen text-slate-800">

    <header class="sticky top-0 z-20 bg-stone-900 text-white shadow-lg">
        <div class="container mx-auto px-2.5 py-2 md:px-4 md:py-2.5">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h1 class="text-sm md:text-lg font-black tracking-tight">Angkringan Mobil</h1>
                </div>

                <div class="flex rounded-full bg-white/10 p-1 gap-1">
                    <button type="button" data-tab="kasir" class="tab-btn active rounded-full px-3 py-1.5 text-[11px] font-bold transition-all">Kasir</button>
                    <button type="button" data-tab="laporan" class="tab-btn rounded-full px-3 py-1.5 text-[11px] font-bold transition-all">Laporan</button>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-2.5 py-2 md:px-4 md:py-3 max-w-5xl">
        <div class="grid gap-3">

            <section id="tab-kasir" class="tab-panel">
                <div class="grid gap-4 lg:grid-cols-[1.45fr_0.95fr]">
                    <div class="space-y-4">
                        <div class="bg-white rounded-2xl border border-stone-200 shadow-sm p-3 sm:p-4 md:p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <div>
                                    <h2 class="text-base font-black text-stone-800">Pilih Menu</h2>
                                    <p class="text-[11px] text-stone-500">Menu terlaris di depan, sisanya tinggal cari</p>
                                </div>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black text-amber-700 uppercase tracking-[0.18em]">POS</span>
                            </div>

                            <div class="relative mb-4">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </span>
                                <input type="text" id="cariMenu" placeholder="Cari menu atau kategori..." autocomplete="off"
                                    class="w-full border border-stone-200 rounded-2xl py-3 pl-10 pr-4 text-sm text-stone-700 focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-200 transition shadow-sm">

                                <ul id="suggestBox" class="absolute z-30 left-0 right-0 top-full mt-1 bg-white border border-stone-200 rounded-2xl shadow-xl hidden max-h-64 overflow-y-auto divide-y divide-stone-100"></ul>
                            </div>

                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-xs font-black uppercase tracking-[0.2em] text-stone-500">Top menu</h3>
                                <span class="text-[11px] text-stone-500">Klik untuk masuk keranjang</span>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <?php if (!empty($featuredMenus)): ?>
                                    <?php foreach ($featuredMenus as $menu): ?>
                                        <button onclick="tambahKeLaporan(<?= (int)$menu['id']; ?>, '<?= addslashes($menu['nama_item']); ?>', <?= (int)$menu['harga']; ?>)"
                                            class="text-left p-2.5 sm:p-3 rounded-2xl border border-stone-200 bg-stone-50 hover:bg-amber-50 active:scale-[0.98] transition-all shadow-sm">
                                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-amber-700 truncate"><?= htmlspecialchars($menu['kategori']); ?></span>
                                            <span class="mt-1 block text-sm font-bold text-stone-800 leading-tight"><?= htmlspecialchars($menu['nama_item']); ?></span>

                                            <?php if (stripos($menu['nama_item'], 'gorengan') !== false || stripos($menu['nama_item'], 'sundukan') !== false): ?>
                                                <span class="mt-2 block text-[11px] font-bold text-blue-600">Input Manual ✏️</span>
                                            <?php else: ?>
                                                <span class="mt-2 block text-[11px] font-bold text-stone-600">Rp <?= number_format($menu['harga'], 0, ',', '.'); ?></span>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="col-span-full text-center text-sm text-gray-500 py-6">Database menu masih kosong.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div id="cart-panel" class="bg-white rounded-3xl border border-stone-200 shadow-sm p-3 sm:p-4 md:p-5">
                            <div class="flex items-center justify-between pb-3 mb-3 border-b border-stone-200">
                                <div>
                                    <h2 class="text-base font-black text-stone-700">Pesanan</h2>
                                </div>
                                <span class="rounded-full bg-stone-100 px-2.5 py-1 text-[10px] font-black text-stone-600 uppercase tracking-[0.18em]">Order</span>
                            </div>

                            <div id="laporan-list" class="space-y-2 overflow-y-auto pr-1 pb-28">
                                <p class="text-gray-400 text-center py-10 text-sm" id="laporan-kosong">Belum ada pesanan.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fixed left-0 right-0 bottom-0 z-30 px-2.5 pb-2.5">
                    <div class="mx-auto max-w-5xl rounded-2xl border border-stone-200 bg-white/95 shadow-[0_-8px_28px_rgba(0,0,0,0.08)] backdrop-blur-sm p-3">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-bold text-stone-500">Total</span>
                            <span class="text-xl font-black text-amber-700" id="total-penjualan">Rp 0</span>
                        </div>
                        <button onclick="simpanLaporan()"
                            class="w-full bg-stone-900 hover:bg-black text-white font-bold py-3 px-4 rounded-2xl shadow-lg active:scale-[0.98] transition-all">
                            Simpan
                        </button>
                    </div>
                </div>
            </section>

            <section id="tab-laporan" class="tab-panel hidden">
                <div class="space-y-3 overflow-hidden">
                    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm p-3 md:p-4 overflow-hidden">
                        <div class="mb-3 bg-amber-50 rounded-2xl border border-amber-200 p-3">
                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-amber-200">
                                <h3 class="text-sm font-black text-stone-700">Catat Pengeluaran</h3>
                                <span class="text-[10px] text-stone-500">Belanja / keluar uang</span>
                            </div>

                            <div class="grid gap-2.5">
                                <label class="grid gap-1">
                                    <span class="text-[11px] font-bold text-stone-500">Nominal</span>
                                    <input id="pengeluaranNominal" type="number" min="1" placeholder="Contoh: 25000"
                                        class="w-full border border-stone-200 rounded-2xl px-2.5 py-2 text-sm text-stone-700 focus:outline-none focus:ring-2 focus:ring-amber-200">
                                </label>

                                <label class="grid gap-1">
                                    <span class="text-[11px] font-bold text-stone-500">Catatan</span>
                                    <textarea id="pengeluaranCatatan" rows="3" placeholder="Contoh: beli gas, plastik, telur"
                                        class="w-full border border-stone-200 rounded-2xl px-2.5 py-2 text-sm text-stone-700 focus:outline-none focus:ring-2 focus:ring-amber-200"></textarea>
                                </label>

                                <button id="simpanPengeluaranBtn" type="button"
                                    class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 px-3 rounded-2xl shadow-lg active:scale-[0.98] transition-all">
                                    Simpan Pengeluaran
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between mb-3">
                            <div>
                                <h2 class="text-base font-black text-stone-700">Laporan Penjualan</h2>
                                <p class="text-xs text-stone-500">Pilih tanggal untuk melihat data harian</p>
                            </div>

                            <form method="GET" action="#laporan" class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
                                <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal_dipilih); ?>"
                                    class="border border-stone-200 rounded-xl px-2.5 py-2 text-sm text-stone-700 focus:outline-none focus:ring-2 focus:ring-amber-200 w-full sm:w-44">
                                <button type="submit" class="bg-stone-800 hover:bg-black text-white font-bold px-3 py-2 rounded-xl transition-all text-sm">
                                    Tampilkan
                                </button>
                            </form>
                        </div>

                        <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-3 min-w-0">
                            <div class="rounded-2xl bg-stone-50 border border-stone-200 p-3 min-w-0">
                                <div class="text-[11px] uppercase tracking-[0.18em] text-stone-500">Tanggal</div>
                                <div class="font-bold text-stone-800"><?= date('d F Y', strtotime($tanggal_dipilih)); ?></div>
                            </div>
                            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-3 min-w-0">
                                <div class="text-[11px] uppercase tracking-[0.18em] text-emerald-700">Total penjualan hari ini</div>
                                <div class="text-base font-black text-emerald-700">Rp <?= number_format($grand_total, 0, ',', '.'); ?></div>
                            </div>
                            <div class="rounded-2xl bg-red-50 border border-red-200 p-3 min-w-0">
                                <div class="text-[11px] uppercase tracking-[0.18em] text-red-700">Total pengeluaran hari ini</div>
                                <div class="text-base font-black text-red-600">Rp <?= number_format($pengeluaran_total, 0, ',', '.'); ?></div>
                            </div>
                            <div class="rounded-2xl bg-amber-50 border border-amber-200 p-3 sm:col-span-2 min-w-0">
                                <div class="text-[11px] uppercase tracking-[0.18em] text-amber-700">Total Pendapatan bersih</div>
                                <div class="text-2xl font-black text-amber-800">Rp <?= number_format(max(0, $grand_total - $pengeluaran_total), 0, ',', '.'); ?></div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-stone-200 bg-stone-50 p-3">
                            <div class="mb-3">
                                <h3 class="text-sm font-black text-stone-700">Jurnal Harian</h3>
                            </div>

                            <div class="overflow-x-auto rounded-2xl border border-stone-200 bg-white max-w-full">
                                <table class="w-full text-left border-collapse table-fixed">
                                    <thead class="bg-stone-100 text-stone-700 uppercase text-[11px]">
                                        <tr>
                                            <th class="p-3 font-bold w-[20%]">Waktu</th>
                                            <th class="p-3 font-bold w-[50%]">Catatan</th>
                                            <th class="p-3 font-bold text-right w-[30%]">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-stone-100 text-sm text-stone-700">
                                        <?php if (!empty($laporan_harian)): ?>
                                            <?php foreach ($laporan_harian as $item): ?>
                                                <tr class="<?= $item['jenis'] === 'pengeluaran' ? 'bg-red-50/60 hover:bg-red-100' : 'hover:bg-stone-50'; ?>">
                                                    <td class="p-3 text-stone-500 align-top"><?= date('H:i', strtotime($item['waktu'])); ?> WIB</td>
                                                    <td class="p-3 font-medium text-stone-800 align-top break-words"><?= htmlspecialchars($item['keterangan']); ?></td>
                                                    <td class="p-3 text-right font-black align-top <?= htmlspecialchars($item['nominal_class']); ?>">
                                                        Rp <?= number_format((int)$item['nominal'], 0, ',', '.'); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="p-8 text-center text-gray-400">Belum ada data pada tanggal ini.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div id="confirmModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/45 px-3">
        <div class="w-full max-w-md rounded-3xl bg-white p-4 shadow-2xl">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-lg font-black text-stone-800">Konfirmasi Pesanan</h3>
                    <p class="text-sm text-stone-500">Cek kembali pesanan sebelum simpan.</p>
                </div>
                <button id="closeConfirmModal" type="button" class="text-stone-400 hover:text-stone-700 text-xl font-bold">✕</button>
            </div>

            <div id="confirmOrderList" class="space-y-2 max-h-64 overflow-y-auto pr-1"></div>

            <div class="mt-4 border-t border-stone-200 pt-4 flex items-center justify-between">
                <span class="text-sm font-bold text-stone-500">Total</span>
                <span id="confirmTotal" class="text-xl font-black text-amber-700">Rp 0</span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <button id="cancelConfirmBtn" type="button" class="bg-stone-100 hover:bg-stone-200 text-stone-700 font-bold py-3 rounded-2xl">
                    Batal
                </button>
                <button id="confirmSaveBtn" type="button" class="bg-stone-900 hover:bg-black text-white font-bold py-3 rounded-2xl">
                    Simpan Sekarang
                </button>
            </div>
        </div>
    </div>

    <script>
        const daftarMenu = <?php echo json_encode($menus); ?>;
        let dataLaporan = {};

        const inputCari = document.getElementById('cariMenu');
        const suggestBox = document.getElementById('suggestBox');
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabPanels = document.querySelectorAll('.tab-panel');
        const confirmModal = document.getElementById('confirmModal');
        const confirmOrderList = document.getElementById('confirmOrderList');
        const confirmTotal = document.getElementById('confirmTotal');
        const closeConfirmModal = document.getElementById('closeConfirmModal');
        const cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
        const confirmSaveBtn = document.getElementById('confirmSaveBtn');
        const pengeluaranNominal = document.getElementById('pengeluaranNominal');
        const pengeluaranCatatan = document.getElementById('pengeluaranCatatan');
        const simpanPengeluaranBtn = document.getElementById('simpanPengeluaranBtn');

        function setActiveTab(tabName) {
            tabButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabName);
            });

            tabPanels.forEach(panel => {
                panel.classList.toggle('hidden', panel.id !== 'tab-' + tabName);
            });

            if (tabName === 'laporan') {
                window.location.hash = 'laporan';
            } else {
                window.location.hash = 'kasir';
            }
        }

        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.tab === 'laporan') {
                    window.location.hash = 'laporan';
                    window.location.reload();
                    return;
                }

                setActiveTab(btn.dataset.tab);
            });
        });

        const currentHash = (window.location.hash || '#kasir').replace('#', '');
        if (currentHash === 'laporan') {
            setActiveTab('laporan');
        } else {
            setActiveTab('kasir');
        }

        inputCari.addEventListener('input', function () {
            const keyword = this.value.toLowerCase().trim();
            suggestBox.innerHTML = '';

            if (keyword.length === 0) {
                suggestBox.classList.add('hidden');
                return;
            }

            const hasilCari = daftarMenu.filter(menu =>
                menu.nama_item.toLowerCase().includes(keyword) ||
                menu.kategori.toLowerCase().includes(keyword)
            );

            if (hasilCari.length > 0) {
                suggestBox.classList.remove('hidden');

                hasilCari.forEach(menu => {
                    const li = document.createElement('li');
                    li.className = 'p-3 hover:bg-amber-50 cursor-pointer flex justify-between items-center transition';

                    const namaLower = menu.nama_item.toLowerCase();
                    const isManualInput = namaLower.includes('gorengan') || namaLower.includes('sundukan');
                    const hargaTeks = isManualInput
                        ? '<span class="text-xs text-blue-600 font-bold">Manual ✏️</span>'
                        : `<span class="text-xs font-bold text-stone-600">Rp ${parseInt(menu.harga).toLocaleString('id-ID')}</span>`;

                    li.innerHTML = `
                        <div class="flex flex-col">
                            <span class="font-bold text-stone-800 text-sm">${menu.nama_item}</span>
                            <span class="text-[10px] text-stone-400 uppercase">${menu.kategori}</span>
                        </div>
                        ${hargaTeks}
                    `;

                    li.onclick = () => {
                        tambahKeLaporan(menu.id, menu.nama_item, menu.harga);
                        inputCari.value = '';
                        suggestBox.classList.add('hidden');
                        inputCari.focus();
                    };

                    suggestBox.appendChild(li);
                });
            } else {
                suggestBox.classList.remove('hidden');
                suggestBox.innerHTML = '<li class="p-4 text-sm text-gray-500 text-center italic">Menu tidak ditemukan</li>';
            }
        });

        document.addEventListener('click', function (e) {
            if (!inputCari.contains(e.target) && !suggestBox.contains(e.target)) {
                suggestBox.classList.add('hidden');
            }
        });

        function tambahKeLaporan(id, nama, harga) {
            let finalHarga = parseInt(harga);
            let cartId = id;
            const namaLower = nama.toLowerCase();

            if (namaLower.includes('gorengan') || namaLower.includes('sundukan')) {
                const inputNominal = prompt(`Masukkan nominal pembelian untuk ${nama} (contoh: 5000):`, '');
                if (inputNominal === null || inputNominal.trim() === '') return;

                finalHarga = parseInt(inputNominal);
                if (isNaN(finalHarga) || finalHarga <= 0) {
                    alert('Nominal yang dimasukkan tidak valid!');
                    return;
                }

                cartId = id + '_' + finalHarga;
            }

            if (dataLaporan[cartId]) {
                dataLaporan[cartId].qty += 1;
            } else {
                dataLaporan[cartId] = { nama: nama, harga: finalHarga, qty: 1 };
            }

            renderLaporan();
        }

        function tambahQtyCart(cartId) {
            if (dataLaporan[cartId]) {
                dataLaporan[cartId].qty += 1;
                renderLaporan();
            }
        }

        function kurangiQty(cartId) {
            if (dataLaporan[cartId]) {
                dataLaporan[cartId].qty -= 1;
                if (dataLaporan[cartId].qty <= 0) {
                    delete dataLaporan[cartId];
                }
            }
            renderLaporan();
        }

        function renderLaporan() {
            const listContainer = document.getElementById('laporan-list');
            listContainer.innerHTML = '';

            let total = 0;
            const keys = Object.keys(dataLaporan);

            if (keys.length === 0) {
                listContainer.innerHTML = '<p class="text-gray-400 text-center py-10 text-sm" id="laporan-kosong">Belum ada pesanan.</p>';
                document.getElementById('total-penjualan').innerText = 'Rp 0';
                document.getElementById('total-penjualan').removeAttribute('data-value');
                return;
            }

            keys.forEach(cartId => {
                const item = dataLaporan[cartId];
                const subtotal = item.harga * item.qty;
                total += subtotal;

                const minusButtonIcon = item.qty <= 1
                    ? '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>'
                    : '-';
                const minusButtonClass = item.qty <= 1
                    ? 'bg-red-100 hover:bg-red-200 text-red-700'
                    : 'bg-stone-100 hover:bg-stone-200 text-stone-700';

                const itemHTML = `
                    <div class="flex justify-between items-center gap-3 bg-stone-50 p-3 rounded-2xl border border-stone-200">
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-stone-800 text-sm leading-tight truncate">${item.nama}</div>
                            <div class="text-[11px] text-stone-500 font-medium">Rp ${item.harga.toLocaleString('id-ID')}</div>
                        </div>
                        <div class="flex items-center gap-2 bg-white p-1 rounded-xl border border-stone-200">
                            <button onclick="kurangiQty('${cartId}')" class="w-8 h-8 ${minusButtonClass} rounded-lg flex items-center justify-center text-sm font-black active:scale-95 transition">${minusButtonIcon}</button>
                            <span class="font-black text-sm w-5 text-center">${item.qty}</span>
                            <button onclick="tambahQtyCart('${cartId}')" class="w-8 h-8 bg-amber-100 hover:bg-amber-200 text-amber-700 rounded-lg flex items-center justify-center text-sm font-black active:scale-95 transition">+</button>
                        </div>
                    </div>
                `;
                listContainer.innerHTML += itemHTML;
            });

            document.getElementById('total-penjualan').innerText = 'Rp ' + total.toLocaleString('id-ID');
            document.getElementById('total-penjualan').dataset.value = total;
        }

        function openConfirmModal() {
            const total = parseInt(document.getElementById('total-penjualan').dataset.value) || 0;
            confirmOrderList.innerHTML = '';
            confirmTotal.innerText = 'Rp ' + total.toLocaleString('id-ID');

            const keys = Object.keys(dataLaporan);
            keys.forEach(cartId => {
                const item = dataLaporan[cartId];
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between gap-3 rounded-2xl bg-stone-50 border border-stone-200 p-3';
                row.innerHTML = `
                    <div>
                        <div class="font-bold text-stone-800 text-sm">${item.nama}</div>
                        <div class="text-[11px] text-stone-500">${item.qty}x • Rp ${item.harga.toLocaleString('id-ID')}</div>
                    </div>
                    <div class="font-black text-stone-700 text-sm">Rp ${(item.harga * item.qty).toLocaleString('id-ID')}</div>
                `;
                confirmOrderList.appendChild(row);
            });

            confirmModal.classList.remove('hidden');
        }

        function closeConfirmModalWindow() {
            confirmModal.classList.add('hidden');
        }

        function simpanLaporan() {
            const total = parseInt(document.getElementById('total-penjualan').dataset.value) || 0;

            if (total === 0) {
                alert('Pilih minimal satu menu terlebih dahulu!');
                return;
            }

            openConfirmModal();
        }

        function submitConfirmedOrder() {
            const total = parseInt(document.getElementById('total-penjualan').dataset.value) || 0;
            let detailString = '';
            const keys = Object.keys(dataLaporan);

            keys.forEach(cartId => {
                const namaLower = dataLaporan[cartId].nama.toLowerCase();
                if (namaLower.includes('gorengan') || namaLower.includes('sundukan')) {
                    detailString += `${dataLaporan[cartId].nama} [Rp ${dataLaporan[cartId].harga}] (${dataLaporan[cartId].qty}x), `;
                } else {
                    detailString += `${dataLaporan[cartId].nama} (${dataLaporan[cartId].qty}x), `;
                }
            });

            detailString = detailString.replace(/, $/, '');

            confirmSaveBtn.disabled = true;
            confirmSaveBtn.innerText = 'Menyimpan...';

            fetch('simpan_transaksi.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    total_penjualan: total,
                    detail_item: detailString
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server web (Apache) merespon error HTTP: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        dataLaporan = {};
                        renderLaporan();
                        closeConfirmModalWindow();
                        alert('✅ Penjualan Berhasil Disimpan!');
                    } else {
                        closeConfirmModalWindow();
                        alert('❌ Gagal dari PHP: ' + data.message);
                    }
                } catch (err) {
                    console.error('Respons PHP Rusak:', text);
                    closeConfirmModalWindow();
                    alert('⚠️ Terjadi Error Sistem (PHP Crash)!\n\nPesan Error:\n' + text.substring(0, 200));
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                closeConfirmModalWindow();
                alert('🔌 Koneksi Gagal! Pastikan WiFi aktif dan XAMPP (Apache & MySQL) dalam keadaan RUNNING.');
            })
            .finally(() => {
                confirmSaveBtn.disabled = false;
                confirmSaveBtn.innerText = 'Simpan Sekarang';
            });
        }

        simpanPengeluaranBtn.addEventListener('click', function () {
            const nominal = parseInt(pengeluaranNominal.value) || 0;
            const catatan = pengeluaranCatatan.value.trim();

            if (nominal <= 0 || catatan === '') {
                alert('Isi nominal dan catatan pengeluaran terlebih dahulu.');
                return;
            }

            simpanPengeluaranBtn.disabled = true;
            simpanPengeluaranBtn.innerText = 'Menyimpan...';

            fetch('simpan_pengeluaran.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nominal: nominal,
                    catatan: catatan
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server web (Apache) merespon error HTTP: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.status === 'success') {
                        pengeluaranNominal.value = '';
                        pengeluaranCatatan.value = '';
                        alert('✅ Pengeluaran berhasil dicatat!');
                    } else {
                        alert('❌ ' + data.message);
                    }
                } catch (err) {
                    console.error('Respons PHP Rusak:', text);
                    alert('⚠️ Terjadi Error Sistem (PHP Crash)!\n\nPesan Error:\n' + text.substring(0, 200));
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('🔌 Koneksi Gagal! Pastikan WiFi aktif dan XAMPP (Apache & MySQL) dalam keadaan RUNNING.');
            })
            .finally(() => {
                simpanPengeluaranBtn.disabled = false;
                simpanPengeluaranBtn.innerText = 'Simpan Pengeluaran';
            });
        });

        closeConfirmModal.addEventListener('click', closeConfirmModalWindow);
        cancelConfirmBtn.addEventListener('click', closeConfirmModalWindow);
        confirmSaveBtn.addEventListener('click', submitConfirmedOrder);
    </script>
</body>
</html>
