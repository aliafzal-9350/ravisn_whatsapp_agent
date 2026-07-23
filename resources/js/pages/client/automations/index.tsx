import { Head, Link, router } from '@inertiajs/react';
import { Edit2, Pause, Play, Plus, Trash2, Workflow } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
    create as createAutomation,
    destroy as destroyAutomation,
    edit as editAutomation,
    toggle as toggleAutomation,
} from '@/routes/client/automations';

import type { AutomationFlow } from './flow-builder';

interface AutomationsIndexProps {
    flows: AutomationFlow[];
}

export default function AutomationsIndex({ flows }: AutomationsIndexProps) {
    const handleToggleFlow = (id: number) => {
        router.put(
            toggleAutomation.url(id),
            {},
            {
                onSuccess: () =>
                    toast.success('Flow status updated successfully!'),
            },
        );
    };

    const handleDeleteFlow = (flow: AutomationFlow) => {
        if (
            confirm(`Are you sure you want to delete the flow "${flow.name}"?`)
        ) {
            router.delete(destroyAutomation.url(flow.id), {
                onSuccess: () => toast.success('Flow deleted successfully!'),
            });
        }
    };

    return (
        <>
            <Head title="Automation Flows" />

            <div
                className="mx-auto flex max-w-7xl flex-col gap-6 px-1 py-2 text-left"
                dir="ltr"
            >
                <div className="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 text-xs font-bold tracking-widest text-primary uppercase">
                            <Workflow className="h-4 w-4" />
                            <span>Workflow Automation</span>
                        </div>
                        <h1 className="mt-2 text-2xl font-black tracking-tight text-zinc-950 dark:text-white">
                            Automation Flows
                        </h1>
                    </div>

                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <Button
                            asChild
                            className="gap-2 bg-primary text-white hover:bg-primary/95"
                        >
                            <Link href={createAutomation.url()}>
                                <Plus className="h-4 w-4" />
                                <span>New Flow</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="border-b bg-muted/40 text-xs font-bold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Manage
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {flows.map((flow) => (
                                    <tr
                                        key={flow.id}
                                        className="bg-card transition hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-bold text-foreground">
                                                {flow.name}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    className="h-8 gap-1.5 px-2.5"
                                                    size="sm"
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleToggleFlow(
                                                            flow.id,
                                                        )
                                                    }
                                                >
                                                    {flow.is_active ? (
                                                        <Pause className="h-3.5 w-3.5 text-amber-500" />
                                                    ) : (
                                                        <Play className="h-3.5 w-3.5 text-primary" />
                                                    )}
                                                    <span className="hidden sm:inline">
                                                        {flow.is_active
                                                            ? 'Pause'
                                                            : 'Activate'}
                                                    </span>
                                                </Button>
                                                <Button
                                                    asChild
                                                    className="h-8 gap-1.5 px-2.5"
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link
                                                        href={editAutomation.url(
                                                            flow.id,
                                                        )}
                                                    >
                                                        <Edit2 className="h-3.5 w-3.5" />
                                                        <span className="hidden sm:inline">
                                                            Edit
                                                        </span>
                                                    </Link>
                                                </Button>
                                                <Button
                                                    className="h-8 px-2.5 text-rose-600 hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/20"
                                                    size="sm"
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleDeleteFlow(flow)
                                                    }
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {flows.length === 0 && (
                        <div className="border-t border-border bg-card p-14 text-center">
                            <Workflow className="mx-auto mb-4 h-12 w-12 text-primary opacity-80" />
                            <h2 className="text-xl font-bold tracking-tight text-foreground">
                                No automation flows created yet
                            </h2>
                            <p className="mx-auto mt-2 max-w-md text-sm font-medium text-muted-foreground">
                                Get started by designing your first interactive
                                flow. You can also import ready-to-use flows
                                from inside the builder interface.
                            </p>
                            <Button
                                asChild
                                className="mt-6 gap-2 bg-primary text-white shadow-lg shadow-primary/15 hover:bg-primary/95"
                            >
                                <Link href={createAutomation.url()}>
                                    <Plus className="h-4 w-4" />
                                    <span>Create Flow</span>
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

AutomationsIndex.layout = (page: React.ReactNode) => page;
