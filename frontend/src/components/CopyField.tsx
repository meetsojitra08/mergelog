import { CopyButton } from './CopyButton';

export function CopyField({ label, value }: { label: string; value: string }) {
  return <div><label className="mb-1 block text-sm font-medium text-gray-700">{label}</label><div className="flex gap-2"><code className="min-w-0 flex-1 overflow-x-auto rounded-md bg-white px-3 py-2 text-sm text-gray-800">{value}</code><CopyButton value={value} /></div></div>;
}
