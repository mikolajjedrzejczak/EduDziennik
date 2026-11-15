import { FormField } from '@/components/forms/form-field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Option } from '@/types/school';

export function OptionField({
    id,
    label,
    value,
    options,
    onChange,
    error,
    optional = false,
    noneLabel = 'Wszystkie',
}: {
    id: string;
    label: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    error?: string;
    optional?: boolean;
    /** Label of the empty choice when `optional` (filters say "Wszystkie", forms "Bez klasy"). */
    noneLabel?: string;
}) {
    return (
        <FormField id={id} label={label} error={error}>
            {(attributes) => (
                <Select
                    value={value || '_none'}
                    onValueChange={(next) =>
                        onChange(next === '_none' ? '' : next)
                    }
                >
                    <SelectTrigger {...attributes} className="w-full">
                        <SelectValue placeholder="Wybierz…" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="_none" disabled={!optional}>
                            {optional ? noneLabel : 'Wybierz…'}
                        </SelectItem>
                        {options.map((option) => (
                            <SelectItem key={option.id} value={option.id}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
        </FormField>
    );
}
