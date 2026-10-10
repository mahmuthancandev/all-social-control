import { Clock } from 'lucide-react';

interface Cell { day: number; hour: number; score: number; count: number }
interface Data { sample_size: number; cells: Cell[]; hourly: Array<{ hour: number; score: number; count: number }>; top: Cell[] }

const DAYS = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
const SHORT = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

/**
 * Geçmiş gönderilere dayalı ısı haritası. Tek tonlu (sıralı) renk skalası: açık = düşük, koyu = yüksek.
 * Her hücrede tooltip; renk tek başına bilgi taşımaz (en iyi saatler metin olarak da listelenir).
 */
export function BestTimes({ data }: { data: Data | null }) {
    if (!data) {
        return (
            <div className="flex flex-col items-center justify-center py-10 text-center text-sm text-slate-500">
                <Clock className="mb-2 size-5" />
                Yeterli geçmiş veri yok (en az 8 görüntülenme verili gönderi gerekir). Tahmin uydurulmaz.
            </div>
        );
    }

    const max = Math.max(...data.cells.map((c) => c.score), 1);
    const cell = (d: number, h: number) => data.cells.find((c) => c.day === d && c.hour === h);
    const hours = Array.from({ length: 24 }, (_, i) => i);

    return (
        <div>
            <div className="mb-4 flex flex-wrap gap-2">
                {data.top.map((t, i) => (
                    <div key={i} className="rounded-lg border border-surface-700 bg-surface-850 px-3 py-2 text-xs">
                        <p className="font-semibold text-white">{i + 1}. {DAYS[t.day]} {String(t.hour).padStart(2, '0')}:00</p>
                        <p className="text-slate-400">ortalamanın {t.score.toLocaleString('tr-TR', { maximumFractionDigits: 1 })} katı · {t.count} gönderi</p>
                    </div>
                ))}
            </div>
            <div className="overflow-x-auto">
                <table className="border-separate border-spacing-0.5 text-[10px]" aria-label="Gün ve saate göre performans">
                    <thead>
                        <tr><th />{hours.map((h) => <th key={h} className="w-5 font-normal text-slate-500">{h % 3 === 0 ? h : ''}</th>)}</tr>
                    </thead>
                    <tbody>
                        {SHORT.map((d, di) => (
                            <tr key={d}>
                                <th className="pr-2 text-right font-normal text-slate-400">{d}</th>
                                {hours.map((h) => {
                                    const c = cell(di, h);
                                    const t = c ? c.score / max : 0;
                                    return (
                                        <td key={h} title={c ? `${DAYS[di]} ${h}:00 — ortalamanın ${c.score} katı (${c.count} gönderi)` : `${DAYS[di]} ${h}:00 — veri yok`}
                                            className="size-5 rounded-[3px]"
                                            style={{ backgroundColor: c ? `rgba(57, 135, 229, ${0.15 + t * 0.85})` : '#141826' }} />
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="mt-2 text-[11px] text-slate-500">{data.sample_size} gönderiye dayanır · Europe/Istanbul · Görüntülenme, hesap medyanına göre normalize edilmiştir · Koyu = daha iyi</p>
        </div>
    );
}
