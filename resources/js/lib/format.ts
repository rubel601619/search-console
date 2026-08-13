const numberFormatter = new Intl.NumberFormat('en-US');
const compactFormatter = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

export function formatNumber(value: number): string {
    return numberFormatter.format(value);
}

export function formatCompact(value: number): string {
    return compactFormatter.format(value);
}

export function formatPercent(value: number): string {
    return `${(value * 100).toFixed(2)}%`;
}

export function formatChange(value: number | null): string | null {
    if (value === null) {
        return null;
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${value.toFixed(1)}%`;
}

export function formatPosition(value: number): string {
    if (value === 0) {
        return '—';
    }

    return value.toFixed(2);
}

export function deviceLabel(code: string): string {
    return code.charAt(0).toUpperCase() + code.slice(1).toLowerCase();
}

const COUNTRY_NAMES: Record<string, string> = {
    ARE: 'United Arab Emirates', ARG: 'Argentina', AUT: 'Austria', AUS: 'Australia', BEL: 'Belgium',
    BGR: 'Bulgaria', BRA: 'Brazil', BLR: 'Belarus', CAN: 'Canada', CHE: 'Switzerland',
    CHL: 'Chile', CHN: 'China', COL: 'Colombia', CYP: 'Cyprus', CZE: 'Czechia',
    DEU: 'Germany', DNK: 'Denmark', DZA: 'Algeria', EGY: 'Egypt', ESP: 'Spain',
    EST: 'Estonia', FIN: 'Finland', FRA: 'France', GBR: 'United Kingdom', GRC: 'Greece',
    HKG: 'Hong Kong', HRV: 'Croatia', HUN: 'Hungary', IDN: 'Indonesia', IRL: 'Ireland',
    ISR: 'Israel', IND: 'India', IRN: 'Iran', IRQ: 'Iraq', ITA: 'Italy',
    JPN: 'Japan', KOR: 'South Korea', KWT: 'Kuwait', KAZ: 'Kazakhstan', LTU: 'Lithuania',
    LUX: 'Luxembourg', LVA: 'Latvia', MAR: 'Morocco', MEX: 'Mexico', MYS: 'Malaysia',
    NGA: 'Nigeria', NLD: 'Netherlands', NOR: 'Norway', NZL: 'New Zealand', OMN: 'Oman',
    PER: 'Peru', PHL: 'Philippines', PAK: 'Pakistan', POL: 'Poland', PRT: 'Portugal',
    QAT: 'Qatar', ROU: 'Romania', RUS: 'Russia', SAU: 'Saudi Arabia', SWE: 'Sweden',
    SGP: 'Singapore', SVN: 'Slovenia', SVK: 'Slovakia', THA: 'Thailand', TUR: 'Turkey',
    TWN: 'Taiwan', UKR: 'Ukraine', USA: 'United States', VEN: 'Venezuela', VNM: 'Vietnam',
    ZAF: 'South Africa',
};

export function countryLabel(code: string): string {
    return COUNTRY_NAMES[code.toUpperCase()] ?? code;
}

export function appearanceLabel(key: string): string {
    return key
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join(' ');
}
