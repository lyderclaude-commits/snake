'use client';

import { useRef, useState } from 'react';
import { decodePhoto } from '@/core/imagePipeline';
import type { PhotoState } from '@/core/types';

interface Props {
  onPhoto: (photo: PhotoState) => void;
  hasPhoto: boolean;
}

export function PhotoDropzone({ onPhoto, hasPhoto }: Props) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handle = async (file: File | undefined) => {
    if (!file) return;
    setBusy(true);
    setError(null);
    try {
      const decoded = await decodePhoto(file);
      onPhoto({ image: decoded.image, x: 0, y: 0, scale: 1, flipX: false });
    } catch (e) {
      setError(e instanceof Error ? e.message : "Cette image n'a pas pu être ouverte.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="sr-only"
        onChange={(e) => handle(e.target.files?.[0])}
      />
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        disabled={busy}
        className={
          hasPhoto
            ? 'w-full rounded-wk-md border border-wk-border bg-white px-4 py-3 text-[14px] font-semibold text-wk-text2 transition hover:border-wk-border2'
            : 'w-full rounded-wk-md border-2 border-dashed border-wk-primary bg-wk-primary-l px-4 py-6 font-display text-[16px] font-bold text-wk-primary transition hover:bg-wk-bg3'
        }
      >
        {busy ? 'Ouverture…' : hasPhoto ? 'Changer de photo' : '📷  Ajouter ma photo'}
      </button>
      {!hasPhoto && !error && (
        <p className="mt-2 text-center text-[12px] text-wk-text3">
          Ta photo ne quitte jamais ton téléphone.
        </p>
      )}
      {error && (
        <p role="alert" className="mt-2 text-[13px] text-wk-red">
          {error}
        </p>
      )}
    </div>
  );
}
