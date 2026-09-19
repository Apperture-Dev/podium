"use client";

import {
  AssistantRuntimeProvider,
  ThreadPrimitive,
  ComposerPrimitive,
  MessagePrimitive,
  useLocalRuntime,
  type ChatModelAdapter,
} from "@assistant-ui/react";
import { Sparkle, ArrowUp } from "@phosphor-icons/react";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

/**
 * No functional agent behavior in this change (see design.md Non-Goals):
 * this adapter makes no network/LLM call, it always returns the same
 * canned reply so the panel is demoable without pretending to be real.
 */
const mockAgentAdapter: ChatModelAdapter = {
  async run() {
    return {
      content: [
        {
          type: "text",
          text: "El agente de Hostium todavía no está conectado. Esto es solo una vista previa de la interfaz — vuelve pronto.",
        },
      ],
    };
  },
};

const SUGGESTIONS = [
  "Añade un dominio personalizado a este proyecto",
  "Explica los pasos que faltan en la checklist",
  "Muéstrame los últimos despliegues del equipo",
];

function UserMessage() {
  return (
    <MessagePrimitive.Root className="flex justify-end px-4 py-1.5">
      <div className="max-w-[85%] bg-primary text-primary-foreground px-3 py-2 text-sm">
        <MessagePrimitive.Content />
      </div>
    </MessagePrimitive.Root>
  );
}

function AssistantMessage() {
  return (
    <MessagePrimitive.Root className="flex justify-start px-4 py-1.5">
      <div className="max-w-[85%] bg-muted px-3 py-2 text-sm">
        <MessagePrimitive.Content />
      </div>
    </MessagePrimitive.Root>
  );
}

export function AgentEntryPoint() {
  const runtime = useLocalRuntime(mockAgentAdapter);

  return (
    <AssistantRuntimeProvider runtime={runtime}>
      <Sheet modal={false}>
        <Tooltip>
          <TooltipTrigger
            render={
              <SheetTrigger
                render={<Button variant="secondary" size="icon" aria-label="Abrir el asistente" />}
              />
            }
          >
            <Sparkle weight="fill" className="size-4" />
          </TooltipTrigger>
          <TooltipContent>Agente</TooltipContent>
        </Tooltip>
        <SheetContent
          side="right"
          showOverlay={false}
          className="flex flex-col gap-0 p-0"
        >
          <SheetHeader className="border-b py-3">
            <SheetTitle>Nuevo chat</SheetTitle>
          </SheetHeader>
          <ThreadPrimitive.Root className="flex flex-1 flex-col overflow-hidden">
            <ThreadPrimitive.Viewport className="flex-1 overflow-y-auto py-4">
              <ThreadPrimitive.Empty>
                <div className="px-4">
                  <p className="text-xl font-medium mb-4">
                    ¿En qué puedo ayudarte?
                  </p>
                  <div className="flex flex-col gap-2">
                    {SUGGESTIONS.map((suggestion) => (
                      <ThreadPrimitive.Suggestion
                        key={suggestion}
                        prompt={suggestion}
                        send
                        className="border px-3 py-2 text-left text-sm hover:bg-accent"
                      >
                        {suggestion}
                      </ThreadPrimitive.Suggestion>
                    ))}
                  </div>
                </div>
              </ThreadPrimitive.Empty>
              <ThreadPrimitive.Messages
                components={{ UserMessage, AssistantMessage }}
              />
            </ThreadPrimitive.Viewport>
            <ComposerPrimitive.Root className="flex items-end gap-2 border-t p-3">
              <ComposerPrimitive.Input
                placeholder="Escribe un mensaje..."
                rows={1}
                className="flex-1 resize-none bg-transparent px-2 py-2 text-sm outline-none placeholder:text-muted-foreground"
              />
              <ComposerPrimitive.Send
                render={<Button size="icon" aria-label="Enviar mensaje" />}
              >
                <ArrowUp className="size-4" />
              </ComposerPrimitive.Send>
            </ComposerPrimitive.Root>
          </ThreadPrimitive.Root>
        </SheetContent>
      </Sheet>
    </AssistantRuntimeProvider>
  );
}
