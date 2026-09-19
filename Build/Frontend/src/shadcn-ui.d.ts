/**
 * What this module uses from the shadcn/ui runtime.
 *
 * Declared here rather than resolved through shadcn_ui's TypeScript sources on purpose: compiling
 * against another extension's tree pulls in its @types/react as a second, incompatible copy of
 * React's types, and every component then fails to accept a ref. This is the same shape a
 * published .d.ts would have, and it is the contract an external app actually depends on —
 * CONTRACT.md in EXT:shadcn_ui is the authority if the two ever disagree.
 */
declare module '@webconsulting/shadcn-ui/runtime.js' {
  import type { ComponentProps, ComponentType, ReactNode } from 'react';

  export interface ShellApi {
    layout: 'chat-left' | 'full';
    chatOpen: boolean;
    openChat(): void;
    closeChat(): void;
    toggleChat(): void;
    setContext(context: Record<string, unknown>): void;
    openCommandPalette(): void;
    focusComposer(): void;
  }

  export interface AppProps {
    props: Record<string, unknown>;
    shell: ShellApi;
  }

  export interface Typo3Context {
    ajaxUrls: Record<string, string>;
    lang: string;
    theme: 'light' | 'dark';
    user: { name: string } | null;
  }

  export function defineShadcnApp(name: string, component: ComponentType<AppProps>): void;
  export function useTypo3(): Typo3Context;
  export function useShell(): ShellApi;
  export function cn(...classes: Array<string | false | null | undefined>): string;

  type Div = ComponentType<ComponentProps<'div'>>;
  type Variant = { variant?: string; size?: string };

  interface Toast {
    (message: string, options?: { description?: ReactNode }): void;
    success(message: string, options?: { description?: ReactNode }): void;
    error(message: string, options?: { description?: ReactNode }): void;
  }

  export const ui: {
    Alert: ComponentType<ComponentProps<'div'> & Variant>;
    AlertTitle: Div;
    AlertDescription: Div;
    Badge: ComponentType<ComponentProps<'span'> & Variant>;
    Button: ComponentType<ComponentProps<'button'> & Variant>;
    Card: Div;
    CardHeader: Div;
    CardTitle: Div;
    CardDescription: Div;
    CardContent: Div;
    CardFooter: Div;
    Input: ComponentType<ComponentProps<'input'>>;
    Label: ComponentType<ComponentProps<'label'>>;
    Textarea: ComponentType<ComponentProps<'textarea'>>;
    Select: ComponentType<{
      value?: string;
      defaultValue?: string;
      onValueChange?: (value: string) => void;
      children?: ReactNode;
    }>;
    SelectTrigger: ComponentType<ComponentProps<'button'>>;
    SelectValue: ComponentType<{ placeholder?: string }>;
    SelectContent: ComponentType<{ children?: ReactNode }>;
    SelectItem: ComponentType<{ value: string; children?: ReactNode }>;
    Separator: ComponentType<ComponentProps<'div'> & { orientation?: 'horizontal' | 'vertical' }>;
    Table: ComponentType<ComponentProps<'table'>>;
    TableHeader: ComponentType<ComponentProps<'thead'>>;
    TableBody: ComponentType<ComponentProps<'tbody'>>;
    TableRow: ComponentType<ComponentProps<'tr'>>;
    TableHead: ComponentType<ComponentProps<'th'>>;
    TableCell: ComponentType<ComponentProps<'td'>>;
    Tabs: ComponentType<{ value?: string; onValueChange?: (value: string) => void; children?: ReactNode }>;
    TabsList: ComponentType<ComponentProps<'div'>>;
    TabsTrigger: ComponentType<ComponentProps<'button'> & { value: string }>;
    TabsContent: ComponentType<ComponentProps<'div'> & { value: string }>;
    Toaster: ComponentType<Record<string, unknown>>;
    toast: Toast;
  };
}
