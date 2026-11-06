import { createContext, useContext } from 'react';

/** True inside the office frame, whose top bar already carries the bell and the language switch (so a page does not repeat them). */
export const ShellContext = createContext(false);
export const useInShell = () => useContext(ShellContext);
