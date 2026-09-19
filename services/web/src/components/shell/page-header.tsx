import Link from "next/link";
import { CaretRight } from "@phosphor-icons/react/dist/ssr";

export type Breadcrumb = {
  label: string;
  href?: string;
};

export function PageBreadcrumb({ items }: { items: Breadcrumb[] }) {
  return (
    <nav className="flex items-center gap-1.5 text-sm text-muted-foreground mb-6">
      {items.map((item, index) => (
        <span key={`${item.label}-${index}`} className="flex items-center gap-1.5">
          {index > 0 && <CaretRight className="size-3" />}
          {item.href ? (
            <Link href={item.href} className="hover:text-foreground">
              {item.label}
            </Link>
          ) : (
            <span className="text-foreground">{item.label}</span>
          )}
        </span>
      ))}
    </nav>
  );
}

export function PageHeader({
  title,
  subtitle,
  action,
}: {
  title: string;
  subtitle?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex items-start justify-between mb-8">
      <div>
        <h1 className="text-3xl">{title}</h1>
        {subtitle && <p className="text-muted-foreground mt-1.5">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}
