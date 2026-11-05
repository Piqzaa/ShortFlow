// Read both current checkboxes and older pages kept open across an update.
export function characterChoices(root, field = 'characterIds') {
  const select = root.querySelector(`select[name$="[${field}][]"]`);
  if (select?.options || select?.selectedOptions) return Array.from(select.options || select.selectedOptions);
  return Array.from(root.querySelectorAll?.(`input[type="checkbox"][name$="[${field}][]"]`) || []);
}

export function selectedCharacters(root, field = 'characterIds') {
  const select = root.querySelector(`select[name$="[${field}][]"]`);
  if (select?.selectedOptions) return Array.from(select.selectedOptions);
  return characterChoices(root, field).filter(choice => choice.selected ?? choice.checked);
}

export function setCharacters(root, values, field = 'characterIds') {
  for (const choice of characterChoices(root, field)) {
    if ('selected' in choice) choice.selected = values.includes(choice.value);
    else choice.checked = values.includes(choice.value);
  }
}
