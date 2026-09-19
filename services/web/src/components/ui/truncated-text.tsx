"use client";

import { cloneElement, useLayoutEffect, useRef, useState, type ReactElement } from "react";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

type AnyProps = Record<string, unknown>;

/** Wraps an element and only shows a tooltip with the full text when it's actually truncated (scrollWidth > clientWidth). */
export function TruncatedText({
  text,
  render,
}: {
  text: string;
  render: ReactElement<AnyProps>;
}) {
  const nodeRef = useRef<HTMLElement | null>(null);
  const [isTruncated, setIsTruncated] = useState(false);

  useLayoutEffect(() => {
    const el = nodeRef.current;
    if (!el) return;
    const checkTruncation = () => setIsTruncated(el.scrollWidth > el.clientWidth);
    checkTruncation();
    const observer = new ResizeObserver(checkTruncation);
    observer.observe(el);
    return () => observer.disconnect();
  }, [text]);

  // Merged-ref forwarding onto an arbitrary polymorphic element (cloneElement's
  // Slot pattern); the callback only assigns nodeRef.current, it never reads
  // it during render.
  // eslint-disable-next-line react-hooks/refs
  const element = cloneElement(render, {
    ref: (node: HTMLElement | null) => {
      nodeRef.current = node;
    },
    children: text,
  });

  return (
    <Tooltip>
      <TooltipTrigger render={element} disabled={!isTruncated} />
      <TooltipContent>{text}</TooltipContent>
    </Tooltip>
  );
}
