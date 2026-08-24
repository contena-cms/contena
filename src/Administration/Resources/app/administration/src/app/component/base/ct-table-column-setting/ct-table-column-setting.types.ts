export interface TableColumnSetting {
    key: string;
    title: string;
    checked: boolean;
    fixed?: 'left' | 'right' | false;
    required?: boolean;
}
